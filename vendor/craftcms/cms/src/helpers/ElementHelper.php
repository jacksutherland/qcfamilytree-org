<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\helpers;

use Craft;
use craft\base\BlockElementInterface;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\base\FieldInterface;
use craft\db\Query;
use craft\db\Table;
use craft\errors\OperationAbortedException;
use craft\fieldlayoutelements\CustomField;
use yii\base\Exception;

/**
 * Class ElementHelper
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0.0
 */
class ElementHelper
{
    private const URI_MAX_LENGTH = 255;

    /**
     * Generates a new temporary slug.
     *
     * @return string
     * @since 3.2.2
     */
    public static function tempSlug(): string
    {
        return '__temp_' . StringHelper::randomString();
    }

    /**
     * Returns whether the given slug is temporary.
     *
     * @param string $slug
     * @return bool
     * @since 3.2.2
     */
    public static function isTempSlug(string $slug): bool
    {
        return strpos($slug, '__temp_') === 0;
    }

    /**
     * Generates a new slug based on a given string.
     *
     * This is different from [[normalizeSlug()]] in two ways:
     *
     * - Periods and underscores will be converted to dashes, whereas [[normalizeSlug()]] will leave those in-tact.
     * - The string may be converted to ASCII.
     *
     * @param string $str The string
     * @param bool|null $ascii Whether the slug should be converted to ASCII. If null, it will depend on
     * the <config3:limitAutoSlugsToAscii> config setting value.
     * @param string|null $language The language to pull ASCII character mappings for, if needed
     * @return string
     * @since 3.5.0
     */
    public static function generateSlug(string $str, ?bool $ascii = null, ?string $language = null): string
    {
        // Replace periods, underscores, and hyphens with spaces so they get separated with the slugWordSeparator
        // to mimic the default JavaScript-based slug generation.
        $slug = str_replace(['.', '_', '-'], ' ', $str);

        if ($ascii ?? Craft::$app->getConfig()->getGeneral()->limitAutoSlugsToAscii) {
            $slug = StringHelper::toAscii($slug, $language);
        }

        return static::normalizeSlug($slug);
    }

    /**
     * Creates a slug based on a given string.
     *
     * @param string $str
     * @return string
     * @deprecated in 3.5.0. Use [[normalizeSlug()]] instead.
     */
    public static function createSlug(string $str): string
    {
        return static::normalizeSlug($str);
    }

    /**
     * Normalizes a slug.
     *
     * @param string $slug
     * @return string
     * @since 3.5.0
     */
    public static function normalizeSlug(string $slug): string
    {
        // Special case for the homepage
        if ($slug === Element::HOMEPAGE_URI) {
            return $slug;
        }

        // Remove HTML tags
        $slug = StringHelper::stripHtml($slug);

        // Remove inner-word punctuation
        $slug = preg_replace('/[\'"‘’“”\[\]\(\)\{\}:]/u', '', $slug);

        // Make it lowercase
        $generalConfig = Craft::$app->getConfig()->getGeneral();
        if (!$generalConfig->allowUppercaseInSlug) {
            $slug = mb_strtolower($slug);
        }

        // Get the "words". Split on anything that is not alphanumeric or allowed punctuation
        // Reference: http://www.regular-expressions.info/unicode.html
        $words = ArrayHelper::filterEmptyStringsFromArray(preg_split('/[^\p{L}\p{N}\p{M}\._\-]+/u', $slug));

        return implode($generalConfig->slugWordSeparator, $words);
    }

    /**
     * Sets the URI on an element using a given URL format, tweaking its slug if necessary to ensure it's unique.
     *
     * @param ElementInterface $element
     * @throws OperationAbortedException if a unique URI could not be found
     */
    public static function setUniqueUri(ElementInterface $element)
    {
        $uriFormat = $element->getUriFormat();

        // No URL format, no URI.
        if ($uriFormat === null) {
            $element->uri = null;
            return;
        }

        // If the URL format returns an empty string, the URL format probably wrapped everything in a condition
        $testUri = self::_renderUriFormat($uriFormat, $element);
        if ($testUri === '') {
            $element->uri = null;
            return;
        }

        // Does the URL format even have a {slug} tag?
        if (!static::doesUriFormatHaveSlugTag($uriFormat)) {
            // Make sure it's unique
            if (!self::_isUniqueUri($testUri, $element)) {
                throw new OperationAbortedException('Could not find a unique URI for this element');
            }

            $element->uri = $testUri;
            return;
        }

        $generalConfig = Craft::$app->getConfig()->getGeneral();
        $maxSlugIncrement = Craft::$app->getConfig()->getGeneral()->maxSlugIncrement;
        $originalSlug = $element->slug;
        $originalSlugLen = mb_strlen($originalSlug);

        for ($i = 1; $i <= $maxSlugIncrement; $i++) {
            $suffix = ($i !== 1) ? $generalConfig->slugWordSeparator . $i : '';
            $element->slug = $originalSlug . $suffix;
            $testUri = self::_renderUriFormat($uriFormat, $element);

            // Make sure we're not over our max length.
            $testUriLen = mb_strlen($testUri);
            if ($testUriLen > self::URI_MAX_LENGTH) {
                // See how much over we are.
                $overage = $testUriLen - self::URI_MAX_LENGTH;

                // If the slug is too small to be trimmed down, we're SOL
                if ($overage >= $originalSlugLen) {
                    $element->slug = $originalSlug;
                    throw new OperationAbortedException('Could not find a unique URI for this element');
                }

                $trimmedSlug = mb_substr($originalSlug, 0, -$overage);
                if ($generalConfig->slugWordSeparator) {
                    $trimmedSlug = rtrim($trimmedSlug, $generalConfig->slugWordSeparator);
                }
                $element->slug = $trimmedSlug . $suffix;
                $testUri = self::_renderUriFormat($uriFormat, $element);
            }

            if (self::_isUniqueUri($testUri, $element)) {
                // OMG!
                $element->uri = $testUri;
                return;
            }
        }

        $element->slug = $originalSlug;
        throw new OperationAbortedException('Could not find a unique URI for this element');
    }

    /**
     * Renders and normalizes a given element URI Format.
     *
     * @param string $uriFormat
     * @param ElementInterface $element
     * @return string
     */
    private static function _renderUriFormat(string $uriFormat, ElementInterface $element): string
    {
        $variables = [];

        // If the URI format contains {id}/{canonicalId}/{sourceId} but the element doesn't have one yet, preserve the tag
        if (!$element->id) {
            $element->tempId = 'id-' . StringHelper::randomString(10);
            if (strpos($uriFormat, '{id') !== false) {
                $variables['id'] = $element->tempId;
            }
            if (!$element->getCanonicalId()) {
                if (strpos($uriFormat, '{canonicalId') !== false) {
                    $variables['canonicalId'] = $element->tempId;
                }
                if (strpos($uriFormat, '{sourceId') !== false) {
                    $variables['sourceId'] = $element->tempId;
                }
            }
        }

        $uri = Craft::$app->getView()->renderObjectTemplate($uriFormat, $element, $variables);

        // Remove any leading/trailing/double slashes
        $uri = preg_replace('/^\/+|(?<=\/)\/+|\/+$/', '', $uri);

        return $uri;
    }

    /**
     * Tests a given element URI for uniqueness.
     *
     * @param string $testUri
     * @param ElementInterface $element
     * @return bool
     */
    private static function _isUniqueUri(string $testUri, ElementInterface $element): bool
    {
        $query = (new Query())
            ->from(['elements_sites' => Table::ELEMENTS_SITES])
            ->innerJoin(['elements' => Table::ELEMENTS], '[[elements.id]] = [[elements_sites.elementId]]')
            ->where([
                'elements_sites.siteId' => $element->siteId,
                'elements.draftId' => null,
                'elements.revisionId' => null,
                'elements.dateDeleted' => null,
            ]);

        if (Craft::$app->getDb()->getIsMysql()) {
            $query->andWhere([
                'elements_sites.uri' => $testUri,
            ]);
        } else {
            // Postgres is case-sensitive
            $query->andWhere([
                'lower([[elements_sites.uri]])' => mb_strtolower($testUri),
            ]);
        }

        if (($sourceId = $element->getCanonicalId()) !== null) {
            $query->andWhere([
                'not', [
                    'elements.id' => $sourceId,
                ],
            ]);
        }

        return (int)$query->count() === 0;
    }

    /**
     * Returns whether a given URL format has a proper {slug} tag.
     *
     * @param string $uriFormat
     * @return bool
     */
    public static function doesUriFormatHaveSlugTag(string $uriFormat): bool
    {
        return (bool)preg_match('/\bslug\b/', $uriFormat);
    }

    /**
     * Returns a list of sites that a given element supports.
     *
     * Each site is represented as an array with `siteId`, `propagate`, and `enabledByDefault` keys.
     *
     * @param ElementInterface $element The element to return supported site info for
     * @param bool $withUnpropagatedSites Whether to include sites the element is currently not being propagated to
     * @return array
     * @throws Exception if any of the element's supported sites are invalid
     */
    public static function supportedSitesForElement(ElementInterface $element, $withUnpropagatedSites = false): array
    {
        $sites = [];
        $siteUidMap = ArrayHelper::map(Craft::$app->getSites()->getAllSites(true), 'id', 'uid');

        foreach ($element->getSupportedSites() as $site) {
            if (!is_array($site)) {
                $site = [
                    'siteId' => (int)$site,
                ];
            } else {
                if (!isset($site['siteId'])) {
                    throw new Exception('Missing "siteId" key in ' . get_class($element) . '::getSupportedSites()');
                }
                $site['siteId'] = (int)$site['siteId'];
            }

            if (!isset($siteUidMap[$site['siteId']])) {
                continue;
            }

            $site['siteUid'] = $siteUidMap[$site['siteId']];

            $site += [
                'propagate' => true,
                'enabledByDefault' => true,
            ];

            if ($withUnpropagatedSites || $site['propagate']) {
                $sites[] = $site;
            }
        }

        return $sites;
    }

    /**
     * Returns whether changes should be tracked for the given element.
     *
     * @param ElementInterface $element
     * @return bool
     * @since 3.7.4
     */
    public static function shouldTrackChanges(ElementInterface $element): bool
    {
        // todo: remove the tableExists condition after the next breakpoint
        return (
            $element->id &&
            $element->siteSettingsId &&
            $element->duplicateOf === null &&
            $element::trackChanges() &&
            !$element->mergingCanonicalChanges &&
            Craft::$app->getDb()->tableExists(Table::CHANGEDATTRIBUTES)
        );
    }

    /**
     * Returns whether the given element is editable by the current user, taking user permissions into account.
     *
     * @param ElementInterface $element
     * @return bool
     */
    public static function isElementEditable(ElementInterface $element): bool
    {
        if ($element->getIsEditable()) {
            if (Craft::$app->getIsMultiSite()) {
                foreach (static::supportedSitesForElement($element) as $siteInfo) {
                    if (Craft::$app->getUser()->checkPermission('editSite:' . $siteInfo['siteUid'])) {
                        return true;
                    }
                }
            } else {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the editable site IDs for a given element, taking user permissions into account.
     *
     * @param ElementInterface $element
     * @return array
     */
    public static function editableSiteIdsForElement(ElementInterface $element): array
    {
        $siteIds = [];

        if ($element->getIsEditable()) {
            if (Craft::$app->getIsMultiSite()) {
                foreach (static::supportedSitesForElement($element) as $siteInfo) {
                    if (Craft::$app->getUser()->checkPermission('editSite:' . $siteInfo['siteUid'])) {
                        $siteIds[] = $siteInfo['siteId'];
                    }
                }
            } else {
                $siteIds[] = Craft::$app->getSites()->getPrimarySite()->id;
            }
        }

        return $siteIds;
    }

    /**
     * Returns the root element of a given element.
     *
     * @param ElementInterface $element
     * @return ElementInterface
     * @since 3.2.0
     */
    public static function rootElement(ElementInterface $element): ElementInterface
    {
        if ($element instanceof BlockElementInterface) {
            return static::rootElement($element->getOwner());
        }
        return $element;
    }

    /**
     * Returns whether the given element (or its root element if a block element) is a draft.
     *
     * @param ElementInterface $element
     * @return bool
     * @since 3.7.0
     */
    public static function isDraft(ElementInterface $element): bool
    {
        return static::rootElement($element)->getIsDraft();
    }

    /**
     * Returns whether the given element (or its root element if a block element) is a revision.
     *
     * @param ElementInterface $element
     * @return bool
     * @since 3.7.0
     */
    public static function isRevision(ElementInterface $element): bool
    {
        return static::rootElement($element)->getIsRevision();
    }

    /**
     * Returns whether the given element (or its root element if a block element) is a draft or revision.
     *
     * @param ElementInterface $element
     * @return bool
     * @since 3.2.0
     */
    public static function isDraftOrRevision(ElementInterface $element): bool
    {
        $root = static::rootElement($element);
        return $root->getIsDraft() || $root->getIsRevision();
    }

    /**
     * Returns whether the given element (or its root element if a block element) is a canonical element.
     *
     * @param ElementInterface $element
     * @return bool
     * @since 3.7.17
     */
    public static function isCanonical(ElementInterface $element): bool
    {
        $root = static::rootElement($element);
        return $root->getIsCanonical();
    }

    /**
     * Returns whether the given element (or its root element if a block element) is a derivative of another element.
     *
     * @param ElementInterface $element
     * @return bool
     * @since 3.7.17
     */
    public static function isDerivative(ElementInterface $element): bool
    {
        $root = static::rootElement($element);
        return $root->getIsDerivative();
    }

    /**
     * Returns whether the given derivative element is outdated compared to its canonical element.
     *
     * @param ElementInterface $element
     * @return bool
     * @since 3.7.12
     */
    public static function isOutdated(ElementInterface $element): bool
    {
        if ($element->getIsCanonical()) {
            return false;
        }

        $canonical = $element->getCanonical();

        if ($element->dateCreated > $canonical->dateUpdated) {
            return false;
        }

        if (!$element->dateLastMerged) {
            return true;
        }

        return $element->dateLastMerged < $canonical->dateUpdated;
    }

    /**
     * Returns the canonical version of an element.
     *
     * @param ElementInterface $element The source/draft/revision element
     * @param bool $anySite Whether the source element can be retrieved in any site
     * @return ElementInterface
     * @since 3.3.0
     * @deprecated in 3.7.0. Use [[ElementInterface::getCanonical()]] instead.
     */
    public static function sourceElement(ElementInterface $element, bool $anySite = false): ElementInterface
    {
        return $element->getCanonical($anySite);
    }

    /**
     * Given an array of elements, will go through and set the appropriate "next"
     * and "prev" elements on them.
     *
     * @param ElementInterface[] $elements The array of elements.
     */
    public static function setNextPrevOnElements(array $elements)
    {
        /** @var ElementInterface $lastElement */
        $lastElement = null;

        foreach ($elements as $i => $element) {
            if ($lastElement) {
                $lastElement->setNext($element);
                $element->setPrev($lastElement);
            } else {
                $element->setPrev(false);
            }

            $lastElement = $element;
        }

        if ($lastElement) {
            $lastElement->setNext(false);
        }
    }

    /**
     * Returns the root level source key for a given source key/path
     *
     * @param string $sourceKey
     * @return string
     * @since 3.7.25.1
     */
    public static function rootSourceKey(string $sourceKey): string
    {
        $pos = strpos($sourceKey, '/');
        return $pos !== false ? substr($sourceKey, 0, $pos) : $sourceKey;
    }

    /**
     * Returns an element type's source definition based on a given source key/path and context.
     *
     * @param string $elementType The element type class
     * @param string $sourceKey The source key/path
     * @param string|null $context The context
     * @return array|null The source definition, or null if it cannot be found
     */
    public static function findSource(string $elementType, string $sourceKey, ?string $context = null)
    {
        /** @var string|ElementInterface $elementType */
        $path = explode('/', $sourceKey);
        $sources = $elementType::sources($context);

        while (!empty($path)) {
            $key = array_shift($path);
            $source = null;

            foreach ($sources as $testSource) {
                if (isset($testSource['key']) && $testSource['key'] === $key) {
                    $source = $testSource;
                    break;
                }
            }

            if ($source === null) {
                return null;
            }

            // Is that the end of the path?
            if (empty($path)) {
                // If this is a nested source, set the full path on it so we don't forget it
                if ($source['key'] !== $sourceKey) {
                    $source['keyPath'] = $sourceKey;
                }

                return $source;
            }

            // Prepare for searching nested sources
            $sources = $source['nested'] ?? [];
        }

        return null;
    }

    /**
     * Returns the description of a field’s translation support.
     *
     * @param string $translationMethod
     * @return string|null
     * @since 3.5.0
     */
    public static function translationDescription(string $translationMethod)
    {
        switch ($translationMethod) {
            case Field::TRANSLATION_METHOD_SITE:
                return Craft::t('app', 'This field is translated for each site.');
            case Field::TRANSLATION_METHOD_SITE_GROUP:
                return Craft::t('app', 'This field is translated for each site group.');
            case Field::TRANSLATION_METHOD_LANGUAGE:
                return Craft::t('app', 'This field is translated for each language.');
            default:
                return null;
        }
    }

    /**
     * Returns the translation key for an element title or custom field, based on the given translation method
     * and translation key format.
     *
     * @param ElementInterface $element
     * @param string $translationMethod
     * @param string|null $translationKeyFormat
     * @return string
     * @since 3.5.0
     */
    public static function translationKey(ElementInterface $element, string $translationMethod, ?string $translationKeyFormat = null): string
    {
        switch ($translationMethod) {
            case Field::TRANSLATION_METHOD_NONE:
                return '1';
            case Field::TRANSLATION_METHOD_SITE:
                return (string)$element->siteId;
            case Field::TRANSLATION_METHOD_SITE_GROUP:
                return (string)$element->getSite()->groupId;
            case Field::TRANSLATION_METHOD_LANGUAGE:
                return $element->getSite()->language;
            default:
                // Translate for each site if a translation key format wasn’t specified
                if ($translationKeyFormat === null) {
                    return (string)$element->siteId;
                }
                return Craft::$app->getView()->renderObjectTemplate($translationKeyFormat, $element);
        }
    }

    /**
     * Returns the content column name for a given field.
     *
     * @param FieldInterface $field
     * @param string|null $columnKey
     * @return string|null
     * @since 3.7.0
     */
    public static function fieldColumnFromField(FieldInterface $field, ?string $columnKey = null): ?string
    {
        if ($field::hasContentColumn()) {
            return static::fieldColumn($field->columnPrefix, $field->handle, $field->columnSuffix, $columnKey);
        }

        return null;
    }

    /**
     * Returns the content column name based on the given field attributes.
     *
     * @param string|null $columnPrefix
     * @param string $handle
     * @param string|null $columnSuffix
     * @param string|null $columnKey
     * @return string
     * @since 3.7.0
     */
    public static function fieldColumn(?string $columnPrefix, string $handle, ?string $columnSuffix, ?string $columnKey = null): string
    {
        return ($columnPrefix ?? Craft::$app->getContent()->fieldColumnPrefix) .
            $handle .
            ($columnKey ? "_$columnKey" : '') .
            ($columnSuffix ? "_$columnSuffix" : '');
    }

    /**
     * Returns whether the attribute on the given element is empty.
     *
     * @param ElementInterface $element
     * @param string $attribute
     * @return bool
     * @since 3.7.56
     */
    public static function isAttributeEmpty(ElementInterface $element, string $attribute): bool
    {
        // See if we're setting a custom field
        $fieldLayout = $element->getFieldLayout();
        if ($fieldLayout) {
            foreach ($fieldLayout->getTabs() as $tab) {
                foreach ($tab->elements as $layoutElement) {
                    if ($layoutElement instanceof CustomField && $layoutElement->attribute() === $attribute) {
                        return $layoutElement->getField()->isValueEmpty($element->getFieldValue($attribute), $element);
                    }
                }
            }
        }

        return empty($element->$attribute);
    }
}
