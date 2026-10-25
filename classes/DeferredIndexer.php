<?php

class PsFacetedsearchDeferredIndexer
{
    const DEFAULT_LIMIT = 50;

    public static function shouldDefer()
    {
        $context = Context::getContext();

        return defined('_PS_ADMIN_DIR_') || (isset($context->controller) && $context->controller instanceof AdminController);
    }

    public static function enqueue($idProduct)
    {
        $idProduct = (int) $idProduct;
        if ($idProduct <= 0) {
            return false;
        }

        $directory = self::getQueueDirectory();
        if (!self::ensureDirectory($directory)) {
            return false;
        }

        return false !== @file_put_contents($directory . '/product-' . $idProduct . '.pending', (string) time(), LOCK_EX);
    }

    public static function process(Ps_Facetedsearch $module, $limit = self::DEFAULT_LIMIT)
    {
        $directory = self::getQueueDirectory();
        if (!self::ensureDirectory($directory)) {
            return 0;
        }

        $lock = @fopen($directory . '/processor.lock', 'c');
        if (!$lock || !@flock($lock, LOCK_EX | LOCK_NB)) {
            if ($lock) {
                @fclose($lock);
            }

            return 0;
        }

        $processed = 0;
        $invalidateCache = false;
        try {
            Shop::setContext(Shop::CONTEXT_ALL);
            $files = glob($directory . '/product-*.pending') ?: [];
            sort($files, SORT_STRING);
            foreach (array_slice($files, 0, max(1, (int) $limit)) as $pendingFile) {
                if (!preg_match('/product-(\d+)\.pending$/', $pendingFile, $matches)) {
                    continue;
                }
                $workingFile = substr($pendingFile, 0, -8) . '.working';
                if (!@rename($pendingFile, $workingFile)) {
                    continue;
                }

                try {
                    $idProduct = (int) $matches[1];
                    $module->indexProductPrices($idProduct);
                    $module->indexAttributes($idProduct);
                    @unlink($workingFile);
                    ++$processed;
                    $invalidateCache = true;
                } catch (Throwable $exception) {
                    @rename($workingFile, $pendingFile);
                    PrestaShopLogger::addLog('ps_facetedsearch deferred indexation failed: ' . $exception->getMessage(), 3);
                }
            }

            if ($invalidateCache) {
                $module->invalidateLayeredFilterBlockCache();
            }
        } finally {
            @flock($lock, LOCK_UN);
            @fclose($lock);
        }

        return $processed;
    }

    private static function getQueueDirectory()
    {
        return rtrim(_PS_CACHE_DIR_, '/\\') . '/ps_facetedsearch_deferred';
    }

    private static function ensureDirectory($directory)
    {
        return is_dir($directory) || @mkdir($directory, 0750, true);
    }
}
