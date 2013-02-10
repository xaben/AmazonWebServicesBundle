<?php

namespace Cybernox\AmazonWebServicesBundle\StreamWrapper;

use \S3StreamWrapper as BaseS3StreamWrapper;
use \CFMimeTypes;

class S3StreamWrapper extends BaseS3StreamWrapper
{
    /**
     * {@inheritdoc}
     */
    public function stream_flush()
    {
        if ($this->buffer === null)
        {
            return false;
        }

        list($protocol, $bucket, $object_name) = $this->parse_path($this->path);

        $extension = explode('.', $object_name);
        $extension = array_pop($extension);
        if ('woff' === $extension) {
            $contentType = 'application/x-font-woff';
        } else {
            $contentType = CFMimeTypes::get_mimetype($extension);
        }

        $response = $this->client($protocol)->create_object($bucket, $object_name, array(
            'body' => $this->buffer,
            'contentType' => $contentType,
            'storage' => 'REDUCED_REDUNDANCY',
            'acl'=> 'public-read'
        ));

        $this->seek_position = 0;
        $this->buffer = null;
        $this->eof = true;

        return $response->isOK();
    }

    /**
     * {@inheritdoc}
     */
    public function mkdir($path, $mode, $options)
    {
        // Get the value that was *actually* passed in as mode, and default to 0
        $trace_slice = array_slice(debug_backtrace(), -1);
        $mode = isset($trace_slice[0]['args'][1]) ? decoct($trace_slice[0]['args'][1]) : 0;

        $this->path = $path;
        list($protocol, $bucket, $object_name) = $this->parse_path($path);

        if (in_array($mode, range(700, 799)))
        {
            $acl = AmazonS3::ACL_PUBLIC;
        }
        elseif (in_array($mode, range(600, 699)))
        {
            $acl = AmazonS3::ACL_AUTH_READ;
        }
        else
        {
            $acl = AmazonS3::ACL_PRIVATE;
        }

        $client = $this->client($protocol);
        $region = $client->hostname;
        $response = $client->create_bucket($bucket, $region, $acl);

        return true;
        //return $response->isOK();
    }

    public static function register(\AmazonS3 $s3 = null, $protocol = 's3')
    {
        self::$_clients[$protocol] = $s3 ? $s3 : new \AmazonS3();

        return stream_wrapper_register($protocol, __CLASS__);
    }
}