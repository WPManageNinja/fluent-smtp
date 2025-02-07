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

class History extends \FluentSmtpLib\Google\Collection
{
    protected $collection_key = 'messagesDeleted';
    /**
     * @var string
     */
    public $id;
    protected $labelsAddedType = \FluentSmtpLib\Google\Service\Gmail\HistoryLabelAdded::class;
    protected $labelsAddedDataType = 'array';
    protected $labelsRemovedType = \FluentSmtpLib\Google\Service\Gmail\HistoryLabelRemoved::class;
    protected $labelsRemovedDataType = 'array';
    protected $messagesType = \FluentSmtpLib\Google\Service\Gmail\Message::class;
    protected $messagesDataType = 'array';
    protected $messagesAddedType = \FluentSmtpLib\Google\Service\Gmail\HistoryMessageAdded::class;
    protected $messagesAddedDataType = 'array';
    protected $messagesDeletedType = \FluentSmtpLib\Google\Service\Gmail\HistoryMessageDeleted::class;
    protected $messagesDeletedDataType = 'array';
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
     * @param HistoryLabelAdded[]
     */
    public function setLabelsAdded($labelsAdded)
    {
        $this->labelsAdded = $labelsAdded;
    }
    /**
     * @return HistoryLabelAdded[]
     */
    public function getLabelsAdded()
    {
        return $this->labelsAdded;
    }
    /**
     * @param HistoryLabelRemoved[]
     */
    public function setLabelsRemoved($labelsRemoved)
    {
        $this->labelsRemoved = $labelsRemoved;
    }
    /**
     * @return HistoryLabelRemoved[]
     */
    public function getLabelsRemoved()
    {
        return $this->labelsRemoved;
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
     * @param HistoryMessageAdded[]
     */
    public function setMessagesAdded($messagesAdded)
    {
        $this->messagesAdded = $messagesAdded;
    }
    /**
     * @return HistoryMessageAdded[]
     */
    public function getMessagesAdded()
    {
        return $this->messagesAdded;
    }
    /**
     * @param HistoryMessageDeleted[]
     */
    public function setMessagesDeleted($messagesDeleted)
    {
        $this->messagesDeleted = $messagesDeleted;
    }
    /**
     * @return HistoryMessageDeleted[]
     */
    public function getMessagesDeleted()
    {
        return $this->messagesDeleted;
    }
}
// Adding a class alias for backwards compatibility with the previous class name.
\class_alias(\FluentSmtpLib\Google\Service\Gmail\History::class, 'FluentSmtpLib\\Google_Service_Gmail_History');
