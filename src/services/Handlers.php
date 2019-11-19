<?php

namespace servd\AssetStorage\services;

use Craft;
use craft\base\Component;
use craft\events\GetAssetThumbUrlEvent;
use craft\events\GetAssetUrlEvent;
use craft\helpers\Assets as AssetsHelper;
use craft\helpers\FileHelper;
use craft\helpers\Image as ImageHelper;
use craft\models\AssetTransform;
use servd\AssetStorage\Plugin;
use yii\base\ErrorException;
use yii\base\Event;

class Handlers extends Component
{
    public function getAssetUrlEvent(GetAssetUrlEvent $event)
    {
        $asset = $event->asset;
        $volume = $asset->getVolume();
        $transform = $event->transform;

        if (!ImageHelper::canManipulateAsImage(pathinfo($asset->filename, PATHINFO_EXTENSION))) {
            return AssetsHelper::generateUrl($volume, $asset);
        }

        if (empty($transform)) {
            $transform = new AssetTransform([
                'height' => $asset->height,
                'width' => $asset->width,
                'interlace' => 'line',
            ]);
        }

        if (\is_array($transform)) {
            $transform = new AssetTransform($transform);
        }

        if (\is_string($transform)) {
            $assetTransforms = Craft::$app->getAssetTransforms();
            $transform = $assetTransforms->getTransformByHandle($transform);
        }

        //If the output type is svg, no transform is occuring, just let Craft handle it
        //This should return a link to the CDN path without optimisation
        if (Plugin::$plugin->optimise->outputWillBeSVG($asset, $transform)) {
            return null;
        }

        return Plugin::$plugin->optimise->transformUrl($asset, $transform);
    }

    public function getAssetThumbUrlEvent(GetAssetThumbUrlEvent $event)
    {
        $asset = $event->asset;
        $volume = $asset->getVolume();

        if (!ImageHelper::canManipulateAsImage(pathinfo($asset->filename, PATHINFO_EXTENSION))) {
            return AssetsHelper::generateUrl($volume, $asset);
        }

        $transform = new AssetTransform([
            'height' => $event->height,
            'width' => $event->width,
            'interlace' => 'line',
        ]);

        //If the output type is svg, no transform is occuring, just let Craft handle it
        //This should return a link to the CDN path without optimisation
        if (Plugin::$plugin->optimise->outputWillBeSVG($asset, $transform)) {
            return null;
        }

        return Plugin::$plugin->optimise->transformUrl($asset, $transform);
    }

    public function clearStaticCache(Event $event = null)
    {
        //Clear the cache
        $cachePath = '/nginxcache';

        if (!file_exists($cachePath)) {
            return;
        }

        try {
            FileHelper::clearDirectory($cachePath);
        } catch (ErrorException $e) {
            Craft::error($e->getMessage(), __METHOD__);
        }
    }
}
