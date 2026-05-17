<?php

declare( strict_types=1 );

namespace Render\Autoloader\Attributes;

use Attribute;

#[Attribute( Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD )]
class AutoRun
{
	/**
	 * Create AutoRun attribute.
	 *
	 * @param float       $priority Execution order (lower = earlier)
	 * @param string|null $import   Import strategy: none|require_once|include_once|require|include
	 * @param bool|null   $check    Include in function_exists check before import
	 */
	public function __construct (
		public float   $priority = 10.0,
		public ?string $import = null,
		public ?bool   $check = null,
	) {
	}
}
