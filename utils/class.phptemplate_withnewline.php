<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Phptemplate_withnewline extends \PhpOffice\PhpWord\TemplateProcessor {

	public function __construct( $documentTemplate = null ) {
		parent::__construct( $documentTemplate );
	}

	public static function getAllPlaceholders( $document_file_name ) {
		$phpWord  = \PhpOffice\PhpWord\IOFactory::load( $document_file_name );
		$sections = $phpWord->getSections();
		$text     = '';
		foreach ( $sections as $s ) {
			$els = $s->getElements();
			foreach ( $els as $e ) {
				$class = get_class( $e );
				if ( method_exists( $class, 'getText' ) ) {
					$text .= $e->getText();
				} else {
					$text .= "\n";
				}
			}
		}
		$placeholders = [];
		if ( preg_match_all( '/\\$\\{([^\\}]+)\\}/', $text, $placeholders ) ) {
			return $placeholders[1];
		}

		return [];
	}

	public static function getUnknownPlaceholders( $document_file_name, $placeholders ) {
		$placeholder_names = [];
		foreach ( array_keys( $placeholders ) as $placeholder ) {
			$placeholder_names[] = preg_replace( '/#.+/', '', $placeholder );
		}

		return array_unique( array_diff( self::getAllPlaceholders( $document_file_name ), $placeholder_names ) );
	}

	public static function getPlaceholderStatus( $model_file, $placeholders, $model_title ) {
		if ( empty( $model_file ) ) {
			return [
				'message' => $model_title . ': pas de modèle DOCX associé',
				'status'  => 'info'
			];
		}
		try {
			$unknowns = Phptemplate_withnewline::getUnknownPlaceholders( $model_file, $placeholders );
			if ( ! empty( $unknowns ) ) {
				return [
					'message' => $model_title . ': placeholders DOCX inconnus : ' . implode( ', ', array_map( function ( $p ) {
							return '${' . $p . '}';
						}, $unknowns ) ) . ' ; causes possibles: mauvais type de modèle ou erreur de frappe',
					'status'  => 'warning'
				];
			}
		} catch ( Exception $ex ) {
			return [
				'message' => $model_title . ': modèle DOCX invalide: ' . $ex->getMessage(),
				'status'  => 'error'
			];
		}

		return true;
	}

	public function setValue( $search, $replace, $limit = self::MAXIMUM_REPLACEMENTS_DEFAULT ) {
		$replace = str_replace(
			array( '<br/>', '<br />', '<br>' ), "\n", $replace
		);
		$replace = strip_tags( $replace );
		\PhpOffice\PhpWord\TemplateProcessor::setValue( $search, $replace, $limit ); // TODO: Change the autogenerated stub
	}


	/**
	 * Find and replace macros in the given XML section.
	 *
	 * @param mixed $search
	 * @param mixed $replace
	 * @param string $documentPartXML
	 * @param int $limit
	 *
	 * @return string
	 */
	protected function setValueForPart( $search, $replace, $documentPartXML, $limit ) {
		// Shift-Enter
		if ( is_array( $replace ) ) {
			foreach ( $replace as &$item ) {
				$item = preg_replace( '~\R~u', '</w:t><w:br/><w:t>', $item );
			}
		} else {
			$replace = preg_replace( '~\R~u', '</w:t><w:br/><w:t>', $replace );
		}

		// Note: we can't use the same function for both cases here, because of performance considerations.
		if ( self::MAXIMUM_REPLACEMENTS_DEFAULT === $limit ) {
			return str_replace( $search, $replace, $documentPartXML );
		}
		$regExpEscaper = new PhpOffice\PhpWord\Escaper\RegExp();

		return preg_replace( $regExpEscaper->escape( $search ), $replace, $documentPartXML, $limit );
	}
}
