<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\events;

use yii\base\Event;

/**
 * DefineHtmlEvent is used to define the HTML for a UI component.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.7.0
 */
class DefineHtmlEvent extends Event
{
    /**
     * @var string The UI component’s HTML
     */
    public $html = '';
}
