<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Api;

use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request\EditRequestParser;
use MediaWiki\Extension\WikibaseReconcileEdit\Reconciliation\ItemReconciler;
use MediaWiki\Extension\WikibaseReconcileEdit\ReconciliationException;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\Validator\JsonBodyValidator;
use MediaWiki\Session\SessionProviderInterface;
use RequestContext;
use Status;
use User;
use Wikibase\Lib\Store\EntityRevision;
use Wikibase\Repo\EditEntity\MediawikiEditEntityFactory;
use Wikibase\Repo\WikibaseRepo;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * @license GPL-2.0-or-later
 */
class EditEndpoint extends SimpleHandler {

	/** @var MediawikiEditEntityFactory */
	private $editEntityFactory;

	/** @var EditRequestParser */
	private $editRequestParser;

	/** @var ItemReconciler */
	private $itemReconciler;

	/** @var User */
	private $user;

	/** @var SessionProviderInterface */
	private $sessionProvider;

	public function __construct(
		MediawikiEditEntityFactory $editEntityFactory,
		EditRequestParser $editRequestParser,
		ItemReconciler $itemReconciler,
		User $user,
		SessionProviderInterface $sessionProvider
	) {
		$this->editEntityFactory = $editEntityFactory;
		$this->editRequestParser = $editRequestParser;
		$this->itemReconciler = $itemReconciler;
		$this->user = $user;
		$this->sessionProvider = $sessionProvider;
	}

	public static function factory(
		EditRequestParser $editRequestParser,
		ItemReconciler $itemReconciler
	): self {
		$repo = WikibaseRepo::getDefaultInstance();
		$editEntityFactory = method_exists( $repo, 'getEditEntityFactory' )
			? $repo->getEditEntityFactory() // 1.36+
			: $repo->newEditEntityFactory(); // 1.35

		return new self(
			$editEntityFactory,
			$editRequestParser,
			$itemReconciler,
			// @TODO Inject this, when there is a good way to do that
			RequestContext::getMain()->getUser(),
			RequestContext::getMain()->getRequest()->getSession()->getProvider()
		);
	}

	public function run() {
		// Rest Validator returns null if submitted as form
		$requestBody = $this->getValidatedBody();

		if ( $requestBody === null ) {
			throw new LocalizedHttpException(
				MessageValue::new( 'wikibasereconcileedit-editendpoint-invalid-request-body' ),
				415
			);
		}

		$editToken = $this->getEditToken( $requestBody );

		if ( !$this->user->isRegistered() || !$this->user->matchEditToken( $editToken ) ) {
			throw new LocalizedHttpException(
				MessageValue::new( 'wikibasereconcileedit-unauthorized-access' ),
				403
			);
		}

		// Parse the request body
		try {
			$request = $this->editRequestParser->parseRequestBody( $requestBody );
		} catch ( ReconciliationException $rex ) {
			throw new LocalizedHttpException( $rex->getMessageValue(), 400 );
		}

		$reconcileUrlProperty = $request->reconcilePropertyId();
		$inputEntity = $request->entity();
		$otherItems = $request->otherItems();

		// Reconcile the item
		try {
			$reconciledItem = $this->itemReconciler->reconcileItem(
				$inputEntity,
				$reconcileUrlProperty
			);
		} catch ( ReconciliationException $rex ) {
			throw new LocalizedHttpException( $rex->getMessageValue(), 400 );
		}

		// And make the edit
		$toSave = $reconciledItem->getItem();
		$saveStatus = Status::newGood();

		foreach ( $otherItems as $otherItem ) {
			// don't need to save this again
			if ( $otherItem->getRevision() ) {
				continue;
			}

			// The base item references itself through a statement
			// It will be saved at a later stage so no need to do it here
			if ( $otherItem->getItem() === $toSave ) {
				continue;
			}

			$otherItemEdit = $this->editEntityFactory->newEditEntity(
				$this->user,
				$otherItem->getItem()->getId(),
				false
			);

			$saveStatus->merge( $otherItemEdit->attemptSave(
				$otherItem->getItem(),
				'Reconciliation Edit',
				EDIT_NEW,
				$editToken
			), true );
			if ( !$saveStatus->isOK() ) {
				break;
			}
		}

		$editEntity = $this->editEntityFactory->newEditEntity(
			$this->user,
			$toSave->getId(),
			$reconciledItem->getBaseRevisionId()
		);

		if ( $saveStatus->isOK() ) {
			$saveStatus->merge( $editEntity->attemptSave(
				$toSave,
				'Reconciliation Edit',
				$reconciledItem->isNew() ? EDIT_NEW : EDIT_UPDATE,
				$editToken
			), true );
		}

		// Make some sort of response
		$response = [
			'success' => $saveStatus->isGood()
		];
		if ( $saveStatus->isGood() ) {
			/** @var EntityRevision $entityRevision */
			$entityRevision = $saveStatus->getValue()['revision'];
			$response['entityId'] = $entityRevision->getEntity()->getId()->getSerialization();
			$response['revisionId'] = $entityRevision->getRevisionId();
		}

		return $response;
	}

	public function needsWriteAccess() {
		return true;
	}

	/**
	 * Determines the CSRF token to be used when making edits
	 * @param array $body
	 * @return string
	 */
	protected function getEditToken( array $body ) {
		if ( $this->sessionProvider->safeAgainstCsrf() ) {
			return $this->user->getEditToken();
		} else {
			return $body['token'] ?? '';
		}
	}

	/**
	 * @inheritDoc
	 */
	public function getBodyValidator( $contentType ) {
		if ( $contentType !== 'application/json' ) {
			throw new LocalizedHttpException(
				MessageValue::new( 'wikibasereconcileedit-editendpoint-invalid-content-type' )
					->textParams( 'application/json' ),
				415
			);
		}

		return new JsonBodyValidator( [
			'entity' => [
				ParamValidator::PARAM_REQUIRED => true,
			],
			'reconcile' => [
				ParamValidator::PARAM_REQUIRED => true,
			],
			'token' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
			],
		] );
	}
}
