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
namespace FluentSmtpLib\Google\Service\Gmail\Resource;

use FluentSmtpLib\Google\Service\Gmail\BatchDeleteMessagesRequest;
use FluentSmtpLib\Google\Service\Gmail\BatchModifyMessagesRequest;
use FluentSmtpLib\Google\Service\Gmail\ListMessagesResponse;
use FluentSmtpLib\Google\Service\Gmail\Message;
use FluentSmtpLib\Google\Service\Gmail\ModifyMessageRequest;
/**
 * The "messages" collection of methods.
 * Typical usage is:
 *  <code>
 *   $gmailService = new Google\Service\Gmail(...);
 *   $messages = $gmailService->users_messages;
 *  </code>
 */
class UsersMessages extends \FluentSmtpLib\Google\Service\Resource
{
    /**
     * Deletes many messages by message ID. Provides no guarantees that messages
     * were not already deleted or even existed at all. (messages.batchDelete)
     *
     * @param string $userId The user's email address. The special value `me` can be
     * used to indicate the authenticated user.
     * @param BatchDeleteMessagesRequest $postBody
     * @param array $optParams Optional parameters.
     * @throws \Google\Service\Exception
     */
    public function batchDelete($userId, \FluentSmtpLib\Google\Service\Gmail\BatchDeleteMessagesRequest $postBody, $optParams = [])
    {
        $params = ['userId' => $userId, 'postBody' => $postBody];
        $params = \array_merge($params, $optParams);
        return $this->call('batchDelete', [$params]);
    }
    /**
     * Modifies the labels on the specified messages. (messages.batchModify)
     *
     * @param string $userId The user's email address. The special value `me` can be
     * used to indicate the authenticated user.
     * @param BatchModifyMessagesRequest $postBody
     * @param array $optParams Optional parameters.
     * @throws \Google\Service\Exception
     */
    public function batchModify($userId, \FluentSmtpLib\Google\Service\Gmail\BatchModifyMessagesRequest $postBody, $optParams = [])
    {
        $params = ['userId' => $userId, 'postBody' => $postBody];
        $params = \array_merge($params, $optParams);
        return $this->call('batchModify', [$params]);
    }
    /**
     * Immediately and permanently deletes the specified message. This operation
     * cannot be undone. Prefer `messages.trash` instead. (messages.delete)
     *
     * @param string $userId The user's email address. The special value `me` can be
     * used to indicate the authenticated user.
     * @param string $id The ID of the message to delete.
     * @param array $optParams Optional parameters.
     * @throws \Google\Service\Exception
     */
    public function delete($userId, $id, $optParams = [])
    {
        $params = ['userId' => $userId, 'id' => $id];
        $params = \array_merge($params, $optParams);
        return $this->call('delete', [$params]);
    }
    /**
     * Gets the specified message. (messages.get)
     *
     * @param string $userId The user's email address. The special value `me` can be
     * used to indicate the authenticated user.
     * @param string $id The ID of the message to retrieve. This ID is usually
     * retrieved using `messages.list`. The ID is also contained in the result when
     * a message is inserted (`messages.insert`) or imported (`messages.import`).
     * @param array $optParams Optional parameters.
     *
     * @opt_param string format The format to return the message in.
     * @opt_param string metadataHeaders When given and format is `METADATA`, only
     * include headers specified.
     * @opt_param bool temporaryEeccBypass
     * @return Message
     * @throws \Google\Service\Exception
     */
    public function get($userId, $id, $optParams = [])
    {
        $params = ['userId' => $userId, 'id' => $id];
        $params = \array_merge($params, $optParams);
        return $this->call('get', [$params], \FluentSmtpLib\Google\Service\Gmail\Message::class);
    }
    /**
     * Imports a message into only this user's mailbox, with standard email delivery
     * scanning and classification similar to receiving via SMTP. This method
     * doesn't perform SPF checks, so it might not work for some spam messages, such
     * as those attempting to perform domain spoofing. This method does not send a
     * message. (messages.import)
     *
     * @param string $userId The user's email address. The special value `me` can be
     * used to indicate the authenticated user.
     * @param Message $postBody
     * @param array $optParams Optional parameters.
     *
     * @opt_param bool deleted Mark the email as permanently deleted (not TRASH) and
     * only visible in Google Vault to a Vault administrator. Only used for Google
     * Workspace accounts.
     * @opt_param string internalDateSource Source for Gmail's internal date of the
     * message.
     * @opt_param bool neverMarkSpam Ignore the Gmail spam classifier decision and
     * never mark this email as SPAM in the mailbox.
     * @opt_param bool processForCalendar Process calendar invites in the email and
     * add any extracted meetings to the Google Calendar for this user.
     * @return Message
     * @throws \Google\Service\Exception
     */
    public function import($userId, \FluentSmtpLib\Google\Service\Gmail\Message $postBody, $optParams = [])
    {
        $params = ['userId' => $userId, 'postBody' => $postBody];
        $params = \array_merge($params, $optParams);
        return $this->call('import', [$params], \FluentSmtpLib\Google\Service\Gmail\Message::class);
    }
    /**
     * Directly inserts a message into only this user's mailbox similar to `IMAP
     * APPEND`, bypassing most scanning and classification. Does not send a message.
     * (messages.insert)
     *
     * @param string $userId The user's email address. The special value `me` can be
     * used to indicate the authenticated user.
     * @param Message $postBody
     * @param array $optParams Optional parameters.
     *
     * @opt_param bool deleted Mark the email as permanently deleted (not TRASH) and
     * only visible in Google Vault to a Vault administrator. Only used for Google
     * Workspace accounts.
     * @opt_param string internalDateSource Source for Gmail's internal date of the
     * message.
     * @return Message
     * @throws \Google\Service\Exception
     */
    public function insert($userId, \FluentSmtpLib\Google\Service\Gmail\Message $postBody, $optParams = [])
    {
        $params = ['userId' => $userId, 'postBody' => $postBody];
        $params = \array_merge($params, $optParams);
        return $this->call('insert', [$params], \FluentSmtpLib\Google\Service\Gmail\Message::class);
    }
    /**
     * Lists the messages in the user's mailbox. (messages.listUsersMessages)
     *
     * @param string $userId The user's email address. The special value `me` can be
     * used to indicate the authenticated user.
     * @param array $optParams Optional parameters.
     *
     * @opt_param bool includeSpamTrash Include messages from `SPAM` and `TRASH` in
     * the results.
     * @opt_param string labelIds Only return messages with labels that match all of
     * the specified label IDs. Messages in a thread might have labels that other
     * messages in the same thread don't have. To learn more, see [Manage labels on
     * messages and threads](https://developers.google.com/gmail/api/guides/labels#m
     * anage_labels_on_messages_threads).
     * @opt_param string maxResults Maximum number of messages to return. This field
     * defaults to 100. The maximum allowed value for this field is 500.
     * @opt_param string pageToken Page token to retrieve a specific page of results
     * in the list.
     * @opt_param string q Only return messages matching the specified query.
     * Supports the same query format as the Gmail search box. For example,
     * `"from:someuser@example.com rfc822msgid: is:unread"`. Parameter cannot be
     * used when accessing the api using the gmail.metadata scope.
     * @opt_param bool temporaryEeccBypass
     * @return ListMessagesResponse
     * @throws \Google\Service\Exception
     */
    public function listUsersMessages($userId, $optParams = [])
    {
        $params = ['userId' => $userId];
        $params = \array_merge($params, $optParams);
        return $this->call('list', [$params], \FluentSmtpLib\Google\Service\Gmail\ListMessagesResponse::class);
    }
    /**
     * Modifies the labels on the specified message. (messages.modify)
     *
     * @param string $userId The user's email address. The special value `me` can be
     * used to indicate the authenticated user.
     * @param string $id The ID of the message to modify.
     * @param ModifyMessageRequest $postBody
     * @param array $optParams Optional parameters.
     * @return Message
     * @throws \Google\Service\Exception
     */
    public function modify($userId, $id, \FluentSmtpLib\Google\Service\Gmail\ModifyMessageRequest $postBody, $optParams = [])
    {
        $params = ['userId' => $userId, 'id' => $id, 'postBody' => $postBody];
        $params = \array_merge($params, $optParams);
        return $this->call('modify', [$params], \FluentSmtpLib\Google\Service\Gmail\Message::class);
    }
    /**
     * Sends the specified message to the recipients in the `To`, `Cc`, and `Bcc`
     * headers. For example usage, see [Sending
     * email](https://developers.google.com/gmail/api/guides/sending).
     * (messages.send)
     *
     * @param string $userId The user's email address. The special value `me` can be
     * used to indicate the authenticated user.
     * @param Message $postBody
     * @param array $optParams Optional parameters.
     * @return Message
     * @throws \Google\Service\Exception
     */
    public function send($userId, \FluentSmtpLib\Google\Service\Gmail\Message $postBody, $optParams = [])
    {
        $params = ['userId' => $userId, 'postBody' => $postBody];
        $params = \array_merge($params, $optParams);
        return $this->call('send', [$params], \FluentSmtpLib\Google\Service\Gmail\Message::class);
    }
    /**
     * Moves the specified message to the trash. (messages.trash)
     *
     * @param string $userId The user's email address. The special value `me` can be
     * used to indicate the authenticated user.
     * @param string $id The ID of the message to Trash.
     * @param array $optParams Optional parameters.
     * @return Message
     * @throws \Google\Service\Exception
     */
    public function trash($userId, $id, $optParams = [])
    {
        $params = ['userId' => $userId, 'id' => $id];
        $params = \array_merge($params, $optParams);
        return $this->call('trash', [$params], \FluentSmtpLib\Google\Service\Gmail\Message::class);
    }
    /**
     * Removes the specified message from the trash. (messages.untrash)
     *
     * @param string $userId The user's email address. The special value `me` can be
     * used to indicate the authenticated user.
     * @param string $id The ID of the message to remove from Trash.
     * @param array $optParams Optional parameters.
     * @return Message
     * @throws \Google\Service\Exception
     */
    public function untrash($userId, $id, $optParams = [])
    {
        $params = ['userId' => $userId, 'id' => $id];
        $params = \array_merge($params, $optParams);
        return $this->call('untrash', [$params], \FluentSmtpLib\Google\Service\Gmail\Message::class);
    }
}
// Adding a class alias for backwards compatibility with the previous class name.
\class_alias(\FluentSmtpLib\Google\Service\Gmail\Resource\UsersMessages::class, 'FluentSmtpLib\\Google_Service_Gmail_Resource_UsersMessages');
