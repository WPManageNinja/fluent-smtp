<?php

namespace FluentSmtpLib\GuzzleHttp;

use FluentSmtpLib\Psr\Http\Message\MessageInterface;
interface BodySummarizerInterface
{
    /**
     * Returns a summarized message body.
     */
    public function summarize(\FluentSmtpLib\Psr\Http\Message\MessageInterface $message) : ?string;
}
