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

class Thread extends \FluentSmtpLib\Google\Collection
{
    protected $collection_key = 'messages';
    /**
     * @var string
     */
    public $historyId;
    /**
     * @var string
     */
    public $id;
    protected $messagesType = \FluentSmtpLib\Google\Service\Gmail\Message::class;
    protected $messagesDataType = 'array';
    /**
     * @var string
     */
    public $snippet;
    /**
     * @param string
     */
    public function setHistoryId($historyId)
    {
        $this->historyId = $historyId;
    }
    /**
     * @return string
     */
    public function getHistoryId()
    {
        return $this->historyId;
    }
    /**
     * @param string
     */
    public function setId($id)
    {
        $this->id = $id;
    }
    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }
    /**
     * @param Message[]
     */
    public function setMessages($messages)
    {
        $this->messages = $messages;
    }
    /**
     * @return Message[]
     */
    public function getMessages()
    {
        return $this->messages;
    }
    /**
     * @param string
     */
    public function setSnippet($snippet)
    {
        $this->snippet = $snippet;
    }
    /**
     * @return string
     */
    public function getSnippet()
    {
        return $this->snippet;
    }
}
// Adding a class alias for backwards compatibility with the previous class name.
\class_alias(\FluentSmtpLib\Google\Service\Gmail\Thread::class, 'FluentSmtpLib\\Google_Service_Gmail_Thread');
