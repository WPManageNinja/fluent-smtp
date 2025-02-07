<?php

/*
 * Copyright 2014 Google Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you may not
 * use this file except in compliance with the License. You may obtain a copy of
 * the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations under
 * the License.
 */
namespace FluentSmtpLib\Google\Service\Gmail;

class MessagePart extends \FluentSmtpLib\Google\Collection
{
    protected $collection_key = 'parts';
    protected $bodyType = \FluentSmtpLib\Google\Service\Gmail\MessagePartBody::class;
    protected $bodyDataType = '';
    /**
     * @var string
     */
    public $filename;
    protected $headersType = \FluentSmtpLib\Google\Service\Gmail\MessagePartHeader::class;
    protected $headersDataType = 'array';
    /**
     * @var string
     */
    public $mimeType;
    /**
     * @var string
     */
    public $partId;
    protected $partsType = \FluentSmtpLib\Google\Service\Gmail\MessagePart::class;
    protected $partsDataType = 'array';
    /**
     * @param MessagePartBody
     */
    public function setBody(\FluentSmtpLib\Google\Service\Gmail\MessagePartBody $body)
    {
        $this->body = $body;
    }
    /**
     * @return MessagePartBody
     */
    public function getBody()
    {
        return $this->body;
    }
    /**
     * @param string
     */
    public function setFilename($filename)
    {
        $this->filename = $filename;
    }
    /**
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }
    /**
     * @param MessagePartHeader[]
     */
    public function setHeaders($headers)
    {
        $this->headers = $headers;
    }
    /**
     * @return MessagePartHeader[]
     */
    public function getHeaders()
    {
        return $this->headers;
    }
    /**
     * @param string
     */
    public function setMimeType($mimeType)
    {
        $this->mimeType = $mimeType;
    }
    /**
     * @return string
     */
    public function getMimeType()
    {
        return $this->mimeType;
    }
    /**
     * @param string
     */
    public function setPartId($partId)
    {
        $this->partId = $partId;
    }
    /**
     * @return string
     */
    public function getPartId()
    {
        return $this->partId;
    }
    /**
     * @param MessagePart[]
     */
    public function setParts($parts)
    {
        $this->parts = $parts;
    }
    /**
     * @return MessagePart[]
     */
    public function getParts()
    {
        return $this->parts;
    }
}
// Adding a class alias for backwards compatibility with the previous class name.
\class_alias(\FluentSmtpLib\Google\Service\Gmail\MessagePart::class, 'FluentSmtpLib\\Google_Service_Gmail_MessagePart');
