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

use FluentSmtpLib\Google\Service\Gmail\Draft;
use FluentSmtpLib\Google\Service\Gmail\ListDraftsResponse;
use FluentSmtpLib\Google\Service\Gmail\Message;
/**
 * The "drafts" collection of methods.
 * Typical usage is:
 *  <code>
 *   $gmailService = new Google\Service\Gmail(...);
 *   $drafts = $gmailService->users_drafts;
 *  </code>
 */
class UsersDrafts extends \FluentSmtpLib\Google\Service\Resource
{
    /**
     * Creates a new draft with the `DRAFT` label. (drafts.create)
     *
     * @param string $userId The user's email address. The special value `me` can be
     * used to indicate the authenticated user.
     * @param Draft $postBody
     * @param array $optParams Optional parameters.
     * @return Draft
     * @throws \Google\Service\Exception
     */
    public function create($userId, \FluentSmtpLib\Google\Service\Gmail\Draft $postBody, $optParams = [])
    {
        $params = ['userId' => $userId, 'postBody' => $postBody];
        $params = \array_merge($params, $optParams);
        return $this->call('create', [$params], \FluentSmtpLib\Google\Service\Gmail\Draft::class);
    }
    /**
     * Immediately and permanently deletes the specified draft. Does not simply
     * trash it. (drafts.delete)
     *
     * @param string $userId The user's email address. The special value `me` can be
     * used to indicate the authenticated user.
     * @param string $id The ID of the draft to delete.
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
     * Gets the specified draft. (drafts.get)
     *
     * @param string $userId The user's email address. The special value `me` can be
     * used to indicate the authenticated user.
     * @param string $id The ID of the draft to retrieve.
     * @param array $optParams Optional parameters.
     *
     * @opt_param string format The format to return the draft in.
     * @return Draft
     * @throws \Google\Service\Exception
     */
    public function get($userId, $id, $optParams = [])
    {
        $params = ['userId' => $userId, 'id' => $id];
        $params = \array_merge($params, $optParams);
        return $this->call('get', [$params], \FluentSmtpLib\Google\Service\Gmail\Draft::class);
    }
    /**
     * Lists the drafts in the user's mailbox. (drafts.listUsersDrafts)
     *
     * @param string $userId The user's email address. The special value `me` can be
     * used to indicate the authenticated user.
     * @param array $optParams Optional parameters.
     *
     * @opt_param bool includeSpamTrash Include drafts from `SPAM` and `TRASH` in
     * the results.
     * @opt_param string maxResults Maximum number of drafts to return. This field
     * defaults to 100. The maximum allowed value for this field is 500.
     * @opt_param string pageToken Page token to retrieve a specific page of results
     * in the list.
     * @opt_param string q Only return draft messages matching the specified query.
     * Supports the same query format as the Gmail search box. For example,
     * `"from:someuser@example.com rfc822msgid: is:unread"`.
     * @return ListDraftsResponse
     * @throws \Google\Service\Exception
     */
    public function listUsersDrafts($userId, $optParams = [])
    {
        $params = ['userId' => $userId];
        $params = \array_merge($params, $optParams);
        return $this->call('list', [$params], \FluentSmtpLib\Google\Service\Gmail\ListDraftsResponse::class);
    }
    /**
     * Sends the specified, existing draft to the recipients in the `To`, `Cc`, and
     * `Bcc` headers. (drafts.send)
     *
     * @param string $userId The user's email address. The special value `me` can be
     * used to indicate the authenticated user.
     * @param Draft $postBody
     * @param array $optParams Optional parameters.
     * @return Message
     * @throws \Google\Service\Exception
     */
    public function send($userId, \FluentSmtpLib\Google\Service\Gmail\Draft $postBody, $optParams = [])
    {
        $params = ['userId' => $userId, 'postBody' => $postBody];
        $params = \array_merge($params, $optParams);
        return $this->call('send', [$params], \FluentSmtpLib\Google\Service\Gmail\Message::class);
    }
    /**
     * Replaces a draft's content. (drafts.update)
     *
     * @param string $userId The user's email address. The special value `me` can be
     * used to indicate the authenticated user.
     * @param string $id The ID of the draft to update.
     * @param Draft $postBody
     * @param array $optParams Optional parameters.
     * @return Draft
     * @throws \Google\Service\Exception
     */
    public function update($userId, $id, \FluentSmtpLib\Google\Service\Gmail\Draft $postBody, $optParams = [])
    {
        $params = ['userId' => $userId, 'id' => $id, 'postBody' => $postBody];
        $params = \array_merge($params, $optParams);
        return $this->call('update', [$params], \FluentSmtpLib\Google\Service\Gmail\Draft::class);
    }
}
// Adding a class alias for backwards compatibility with the previous class name.
\class_alias(\FluentSmtpLib\Google\Service\Gmail\Resource\UsersDrafts::class, 'FluentSmtpLib\\Google_Service_Gmail_Resource_UsersDrafts');
