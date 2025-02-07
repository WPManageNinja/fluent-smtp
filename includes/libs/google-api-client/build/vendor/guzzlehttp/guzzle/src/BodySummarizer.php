<?php

namespace FluentSmtpLib\GuzzleHttp;

use FluentSmtpLib\Psr\Http\Message\MessageInterface;
final class BodySummarizer implements \FluentSmtpLib\GuzzleHttp\BodySummarizerInterface
{
    /**
     * @var int|null
     */
    private $truncateAt;
    public function __construct(?int $truncateAt = null)
    {
        $this->truncateAt = $truncateAt;
    }
    /**
     * Returns a summarized message body.
     */
    public function summarize(\FluentSmtpLib\Psr\Http\Message\MessageInterface $message) : ?string
    {
        return $this->truncateAt === null ? \FluentSmtpLib\GuzzleHttp\Psr7\Message::bodySummary($message) : \FluentSmtpLib\GuzzleHttp\Psr7\Message::bodySummary($message, $this->truncateAt);
    }
}
