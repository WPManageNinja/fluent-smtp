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

class ListThreadsResponse extends \FluentSmtpLib\Google\Collection
{
    protected $collection_key = 'threads';
    /**
     * @var string
     */
    public $nextPageToken;
    /**
     * @var string
     */
    public $resultSizeEstimate;
    protected $threadsType = \FluentSmtpLib\Google\Service\Gmail\Thread::class;
    protected $threadsDataType = 'array';
    /**
     * @param string
     */
    public function setNextPageToken($nextPageToken)
    {
        $this->nextPageToken = $nextPageToken;
    }
    /**
     * @return string
     */
    public function getNextPageToken()
    {
        return $this->nextPageToken;
    }
    /**
     * @param string
     */
    public function setResultSizeEstimate($resultSizeEstimate)
    {
        $this->resultSizeEstimate = $resultSizeEstimate;
    }
    /**
     * @return string
     */
    public function getResultSizeEstimate()
    {
        return $this->resultSizeEstimate;
    }
    /**
     * @param Thread[]
     */
    public function setThreads($threads)
    {
        $this->threads = $threads;
    }
    /**
     * @return Thread[]
     */
    public function getThreads()
    {
        return $this->threads;
    }
}
// Adding a class alias for backwards compatibility with the previous class name.
\class_alias(\FluentSmtpLib\Google\Service\Gmail\ListThreadsResponse::class, 'FluentSmtpLib\\Google_Service_Gmail_ListThreadsResponse');
