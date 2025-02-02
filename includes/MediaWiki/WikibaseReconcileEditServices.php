<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki;

use MediaWiki\Extension\WikibaseReconcileEdit\EditStrategy\SimplePutStrategy;
use MediaWiki\Extension\WikibaseReconcileEdit\InputToEntity\FullWikibaseItemInput;
use MediaWiki\Extension\WikibaseReconcileEdit\InputToEntity\MinimalItemInput;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request\EditRequestParser;
use MediaWiki\Extension\WikibaseReconcileEdit\Reconciliation\ItemReconciler;
use MediaWiki\Extension\WikibaseReconcileEdit\Reconciliation\ReconciliationService;
use MediaWiki\MediaWikiServices;
use Psr\Container\ContainerInterface;
use Wikibase\DataModel\Services\Term\PropertyLabelResolver;

/**
 * @license GPL-2.0-or-later
 */
class WikibaseReconcileEditServices {

	private function __construct() {
		// should not be instantiated
	}

	public static function getEditRequestParser( ContainerInterface $services = null ): EditRequestParser {
		return ( $services ?: MediaWikiServices::getInstance() )
			->get( 'WikibaseReconcileEdit.EditRequestParser' );
	}

	public static function getExternalLinks( ContainerInterface $services = null ): ExternalLinks {
		return ( $services ?: MediaWikiServices::getInstance() )
			->get( 'WikibaseReconcileEdit.ExternalLinks' );
	}

	public static function getFullWikibaseItemInput( ContainerInterface $services = null ): FullWikibaseItemInput {
		return ( $services ?: MediaWikiServices::getInstance() )
			->get( 'WikibaseReconcileEdit.FullWikibaseItemInput' );
	}

	public static function getItemReconciler( ContainerInterface $services = null ): ItemReconciler {
		return ( $services ?: MediaWikiServices::getInstance() )
			->get( 'WikibaseReconcileEdit.ItemReconciler' );
	}

	public static function getMinimalItemInput( ContainerInterface $services = null ): MinimalItemInput {
		return ( $services ?: MediaWikiServices::getInstance() )
			->get( 'WikibaseReconcileEdit.MinimalItemInput' );
	}

	public static function getPropertyLabelResolver( ContainerInterface $services = null ): PropertyLabelResolver {
		return ( $services ?: MediaWikiServices::getInstance() )
			->get( 'WikibaseReconcileEdit.PropertyLabelResolver' );
	}

	public static function getReconciliationService( ContainerInterface $services = null ): ReconciliationService {
		return ( $services ?: MediaWikiServices::getInstance() )
			->get( 'WikibaseReconcileEdit.ReconciliationService' );
	}

	public static function getSimplePutStrategy( ContainerInterface $services = null ): SimplePutStrategy {
		return ( $services ?: MediaWikiServices::getInstance() )
			->get( 'WikibaseReconcileEdit.SimplePutStrategy' );
	}

}
