<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Migration.Wordpress
 * @copyright   Copyright (C) 2025 Open Source Matters, Inc.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Event\AbstractEvent;
use Joomla\CMS\Plugin\CMSPlugin;

/**
 * WordPress Migration Plugin
 *
 * @since  1.0.0
 */
class PlgMigrationWordpress extends CMSPlugin
{
    /**
     * Handles the onMigrationConvert event.
     *
     * @param   AbstractEvent  $event  The event object.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function onMigrationConvert(AbstractEvent $event)
    {
        $args = $event->getArguments();
        $sourceCms = $args['sourceCms'] ?? null;
        $filePath = $args['filePath'] ?? null;

        if ($sourceCms !== 'wordpress' || empty($filePath)) {
            return;
        }

        $xmlContent = file_get_contents($filePath);
        if (empty($xmlContent)) {
            return;
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlContent);

        if ($xml === false) {
            return;
        }

        // Register namespaces
        $namespaces = $xml->getNamespaces(true);
        $wp = $namespaces['wp'] ?? 'wp';
        $content = $namespaces['content'] ?? 'content';
        $dc = $namespaces['dc'] ?? 'dc';

        // Map author login to email
        $authorEmailMap = [];
        foreach ($xml->channel->children($wp)->author as $authorNode) {
            $login = (string) $authorNode->children($wp)->author_login;
            $email = (string) $authorNode->children($wp)->author_email;
            $authorEmailMap[$login] = $email;
        }

        $itemList = [];
        $mediaItems = [];
        $position = 1;

        foreach ($xml->channel->item as $item) {
            $wp_ns = $item->children($wp);
            if ((string) $wp_ns->post_type === 'attachment') {
                $mediaItem = [
                    '@type' => 'MediaObject',
                    'name' => (string) $item->title,
                    'url' => (string) $wp_ns->attachment_url,
                    'description' => (string) $item->description,
                    'mediaType' => (string) $item->children($dc)->format ?? null,
                    'dateUploaded' => (new DateTime((string) $item->pubDate))->format(DateTime::ATOM)
                ];
                $mediaItems[] = $mediaItem;
                continue; // Skip further processing for attachments
            }
            $postType = (string) $wp_ns->post_type;

            // Only process posts and pages
            if (($postType !== 'post' && $postType !== 'page') || (string) $wp_ns->status !== 'publish') {
                continue;
            }

            // Get categories and tags
            $categories = [];
            $tags = [];
            foreach ($item->category as $category) {
                $domain = (string) $category['domain'];
                if ($domain === 'category') {
                    $categories[] = (string) $category;
                } elseif ($domain === 'post_tag') {
                    $tags[] = (string) $category;
                }
            }

            // Article body with replaced image URLs
            $contentEncoded = $item->children($content)->encoded;
            $articleBody = (string) $contentEncoded;

            // Replace local image URLs
            foreach ($wp_ns->attachment_url as $attachment) {
                $localUrl = 'http://localhost/wp/wp-content/uploads/';
                $attachmentUrl = (string) $attachment;
                $articleBody = str_replace($localUrl, $attachmentUrl, $articleBody);
            }

            // Convert date to ISO 8601
            $pubDate = new DateTime((string) $item->pubDate);
            $isoDate = $pubDate->format(DateTime::ATOM);

            // Get author info
            $authorLogin = (string) $item->children($dc)->creator;
            $authorEmail = $authorEmailMap[$authorLogin] ?? null;

            // Extract custom fields
            $customFields = [];
            foreach ($wp_ns->postmeta as $meta) {
                $key = (string) $meta->meta_key;
                $value = (string) $meta->meta_value;
                if (substr($key, 0, 1) === '_') {
                    continue;
                }
                $customFields[$key] = $value;
            }

            // Build article
            $article = [
                '@type' => 'Article',
                'headline' => (string) $item->title,
                'articleSection' => $categories ?: ['Uncategorized'],
                'keywords' => implode(', ', $tags),
                'articleBody' => $articleBody,
                'datePublished' => $isoDate,
                'author' => [
                    '@type' => 'Person',
                    'name' => $authorLogin,
                    'email' => $authorEmail
                ],
                'customFields' => $customFields
            ];

            // Add to list
            $itemList[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'item' => $article
            ];
        }

        $final = [
            '@context' => 'http://schema.org',
            '@type' => 'ItemList',
            'itemListElement' => $itemList,
            'mediaItems' => $mediaItems
        ];

        $event->addResult(json_encode($final, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
