<?php
/**
 * This file is management Media package
 *
 * PHP version 7
 *
 * @category    Media
 * @package     Xpressengine\Media
 * @author      XE Developers <developers@xpressengine.com>
 * @copyright   2020 Copyright XEHub Corp. <https://www.xehub.io>
 * @license     http://www.gnu.org/licenses/lgpl-3.0-standalone.html LGPL
 * @link        https://xpressengine.io
 */

namespace Xpressengine\Media;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Xpressengine\Media\Coordinators\Dimension;
use Xpressengine\Media\Exceptions\NotAvailableException;
use Xpressengine\Media\Exceptions\UnknownTypeException;
use Xpressengine\Media\Models\Media;
use Xpressengine\Media\Models\Image;
use Xpressengine\Storage\File;
use Xpressengine\Media\Handlers\MediaHandler;
use Xpressengine\Storage\Storage;

/**
 * Class MediaManager
 *
 * @category    Media
 * @package     Xpressengine\Media
 * @author      XE Developers <developers@xpressengine.com>
 * @copyright   2020 Copyright XEHub Corp. <https://www.xehub.io>
 * @license     http://www.gnu.org/licenses/lgpl-3.0-standalone.html LGPL
 * @link        https://xpressengine.io
 */
class MediaManager
{
    /**
     * Storage instance
     *
     * @var Storage
     */
    protected $storage;

    /**
     * CommandFactory instance
     *
     * @var CommandFactory
     */
    protected $factory;

    /**
     * config data
     *
     * @var array
     */
    protected $config = [];

    /**
     * media handlers
     *
     * @var MediaHandler[]
     */
    protected $handlers = [];

    /**
     * Constructor
     *
     * @param Storage        $storage Storage instance
     * @param CommandFactory $factory CommandFactory instance
     * @param array          $config  config data
     */
    public function __construct(Storage $storage, CommandFactory $factory, array $config)
    {
        $this->storage = $storage;
        $this->factory = $factory;
        $this->config = $config;
    }

    /**
     * Returns handler
     *
     * @param string $type media type
     * @return MediaHandler
     * @throws UnknownTypeException
     */
    public function getHandler($type)
    {
        if (isset($this->handlers[$type]) !== true) {
            throw new UnknownTypeException();
        }

        return $this->handlers[$type];
    }

    /**
     * Returns handler by storage File instance
     *
     * @param File $file file instance
     * @return MediaHandler
     * @throws UnknownTypeException
     */
    public function getHandlerByFile(File $file)
    {
        if (!$type = $this->getFileType($file)) {
            throw new UnknownTypeException();
        }

        return $this->getHandler($type);
    }

    /**
     * ????????? ?????? ????????? ????????? ??????????????? ?????? ?????? ??????
     *
     * @param File $file file instance
     * @return string|null
     */
    public function getFileType(File $file)
    {
        foreach ($this->handlers as $type => $handler) {
            if ($handler->isAvailable($file->mime) === true) {
                return $type;
            }
        }

        return null;
    }

    /**
     * Returns handler by storage Media instance
     *
     * @param Media $media media instance
     * @return MediaHandler
     */
    public function getHandlerByMedia(Media $media)
    {
        return $this->getHandler($media->getType());
    }

    /**
     * ????????? ????????? ?????? ????????? ????????? ??????????????? ??????
     *
     * @param File $file file instance
     * @return Media
     * @throws NotAvailableException
     */
    public function make(File $file)
    {
        return $this->getHandlerByFile($file)->make($file);
    }

    /**
     * ????????? ????????? ???????????? ??????, ?????????????????? ???????????? ??????
     *
     * @param File $file file instance
     * @return Media
     */
    public function cast(File $file)
    {
        return $this->getHandlerByFile($file)->makeModel($file);
    }

    /**
     * ????????? ????????? ???????????? ??????
     *
     * @param File $file file instance
     * @return bool
     */
    public function is(File $file)
    {
        foreach ($this->handlers as $type => $handler) {
            if ($handler->isAvailable($file->mime) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * ????????? ??????
     *
     * @param Media $media media instance
     * @return bool
     */
    public function delete(Media $media)
    {
        $this->metaDelete($media);

        return $this->storage->delete($media);
    }

    /**
     * Meta data ??????
     *
     * @param Media $media media instance
     * @return void
     */
    public function metaDelete(Media $media)
    {
        if ($media->meta) {
            $media->meta->delete();
        }
    }

    /**
     * ????????? ??????
     *
     * @param Media       $media      media instance
     * @param string|null $type       ????????? ?????? ??????
     * @param array|null  $dimensions ????????? ??????
     * @param null        $path       directory for saved
     * @param string|null $disk       disk for saved
     * @param mixed       $option     disk option (ex. aws s3 'visibility: public')
     * @return Collection|Image[]
     */
    public function createThumbnails(
        Media $media,
        $type = null,
        array $dimensions = null,
        $path = null,
        $disk = null,
        $option = []
    ) {
        $type = strtolower($type ?: $this->config['type']);
        $path = $path ?: $this->config['path'];
        $disk = $disk ?: $this->config['disk'];
        $dimensions = $dimensions ?: $this->config['dimensions'];
        $handler = $this->getHandlerByMedia($media);

        if (!$content = $handler->getPicture($media)) {
            return [];
        }

        $thumbnails = [];
        foreach ($dimensions as $code => $dimension) {
            $command = $this->factory->make($type);
            $command->setDimension(new Dimension($dimension['width'], $dimension['height']));

            $thumbnails[] = $this->getHandler(Media::TYPE_IMAGE)
                ->createThumbnails(
                    $content,
                    $command,
                    $code,
                    $disk,
                    $path,
                    $media->getOriginKey(),
                    $option
                );
        }

        return new Collection($thumbnails);
    }

    /**
     * ???????????? ????????? ????????? ?????? ??????
     *
     * @param Media $media media instance
     * @return Collection|Media[]
     */
    public function getDerives(Media $media)
    {
        $files = $media->getRawDerives();

        foreach ($files as $key => $file) {
            $files[$key] = $this->is($file) ? $this->make($file) : null;
        }

        return $files->filter();
    }

    /**
     * ????????? ???????????? ??????, ???????????? ?????? ??????
     *
     * is() method ??? ?????? ????????? ????????? ?????? ????????? ??? ????????? ?????????
     * ????????? handler ?????? ???????????? ????????? ?????? ????????? ???
     *
     * @param string       $type    media type
     * @param MediaHandler $handler media handler
     * @return void
     */
    public function extend($type, MediaHandler $handler)
    {
        $this->handlers[$type] = $handler;
    }

    /**
     * __call
     *
     * @param string     $name      method name
     * @param array|null $arguments arguments
     * @return MediaHandler|null
     */
    public function __call($name, $arguments)
    {
        $name = Str::singular($name);

        if (!array_key_exists($name, $this->handlers)) {
            return null;
        }

        return $this->handlers[$name];
    }
}
