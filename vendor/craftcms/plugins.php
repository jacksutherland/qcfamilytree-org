<?php

$vendorDir = dirname(__DIR__);

return array (
  'craftcms/redactor' => 
  array (
    'class' => 'craft\\redactor\\Plugin',
    'basePath' => $vendorDir . '/craftcms/redactor/src',
    'handle' => 'redactor',
    'aliases' => 
    array (
      '@craft/redactor' => $vendorDir . '/craftcms/redactor/src',
    ),
    'name' => 'Redactor',
    'version' => '2.1.7',
    'description' => 'Edit rich text content in Craft CMS using Redactor by Imperavi.',
    'developer' => 'Pixel & Tonic',
    'developerUrl' => 'https://pixelandtonic.com/',
    'developerEmail' => 'support@craftcms.com',
    'documentationUrl' => 'https://github.com/craftcms/redactor',
  ),
  'dolphiq/sitemap' => 
  array (
    'class' => 'dolphiq\\sitemap\\Sitemap',
    'basePath' => $vendorDir . '/dolphiq/sitemap/src',
    'handle' => 'sitemap',
    'aliases' => 
    array (
      '@dolphiq/sitemap' => $vendorDir . '/dolphiq/sitemap/src',
    ),
    'name' => 'XML Sitemap',
    'version' => '1.0.9',
    'schemaVersion' => '1.0.2',
    'description' => 'Craft 3 plugin that provides an easy way to provide and manage a XML sitemap for search engines like Google and Bing',
    'developer' => 'Dolphiq',
    'developerUrl' => 'https://dolphiq.nl/',
    'documentationUrl' => 'https://github.com/Dolphiq/craft3-plugin-sitemap/blob/master/README.md',
    'changelogUrl' => 'https://github.com/Dolphiq/craft3-plugin-sitemap/blob/master/CHANGELOG.md',
    'hasCpSettings' => true,
    'hasCpSection' => false,
    'components' => 
    array (
      'sitemapService' => 'dolphiq\\sitemap\\services\\SitemapService',
    ),
  ),
);
