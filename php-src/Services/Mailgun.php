<?php

namespace kalanis\EmailMailgun\Services;


use kalanis\EmailApi\Exceptions;
use kalanis\EmailApi\Interfaces;
use kalanis\EmailApi\Basics;
use Mailgun\Exception\HttpClientException;
use Mailgun\Mailgun as libMailgun;


/**
 * Class Mailgun
 * Make and send each mail via Mailgun service
 * @link http://www.mailgun.com/
 * @link https://documentation.mailgun.com/en/latest/
 * @link https://github.com/mailgun/mailgun-php
 */
class Mailgun implements Interfaces\ISending
{
    /** @var Interfaces\ILocalProcessing */
    protected $localProcess = '';
    /** @var string */
    protected $confKey = '';
    /** @var string */
    protected $confTarget = '';

    public function __construct(Interfaces\ILocalProcessing $localProcess, string $confKey = '', string $confTarget = '')
    {
        $this->localProcess = $localProcess;
        $this->confKey = $confKey;
        $this->confTarget = $confTarget;
    }

    public function systemServiceId(): int
    {
        return 6;
    }

    public function canUseService(): bool
    {
        return (bool)$this->confKey
            && (bool)$this->confTarget;
    }

    /**
     * Send mail directly into the service
     *
     * @param Interfaces\IContent $content
     * @param Interfaces\IEmailUser $to
     * @param Interfaces\IEmailUser $from
     * @param Interfaces\IEmailUser $replyTo
     * @param bool $toDisabled
     * @return Basics\Result
     */
    public function sendEmail(Interfaces\IContent $content, Interfaces\IEmailUser $to, ?Interfaces\IEmailUser $from = null, ?Interfaces\IEmailUser $replyTo = null, $toDisabled = false): Basics\Result
    {
        if ($toDisabled) {
            try {
                $this->enableMailOnRemote($to);
                $this->localProcess->enableMailLocally($to);
            } catch (Exceptions\EmailException $ex) {
                return new Basics\Result(false, $ex->getMessage(), 0);
            }
        }

        $libMailgun = libMailgun::create($this->confKey);

        $data = [
            'to' => $to->getEmail(),
            'subject' => $content->getSubject(),
            'html' => $content->getHtmlBody(),
            'text' => $content->getPlainBody(),
        ];

        if ($from) {
            $data['from'] = $from->getEmail();
        }

        if ($content->getAttachments()) {
            $attachments = [];
            $inline = [];
            foreach ($content->getAttachments() as $attachment) {
                if (Interfaces\IContentAttachment::TYPE_INLINE == $attachment->getType()) {
                    $inline[] = [
                        'filePath' => $attachment->getLocalPath(),
                        'filename' => $attachment->getFileName(),
                    ];
                } else {
                    if ($attachment->getLocalPath()) {
                        $attachments[] = [
                            'filePath' => $attachment->getLocalPath(),
                            'filename' => $attachment->getFileName(),
                        ];
                    } else {
                        $attachments[] = [
                            'fileContent' => $attachment->getFileContent(),
                            'filename' => $attachment->getFileName(),
                        ];
                    }

                }
            }
            $data['attachment'] = $attachments;
            $data['inline'] = $inline;
        }

        try {
            $result = $libMailgun->messages()->send($this->confTarget, $data);
            return new Basics\Result(true, $result);
        } catch (HttpClientException $ex) {
            return new Basics\Result(false, $ex);
        }
    }

    /**
     * Remove address from internal bounce log on Mailgun
     * @param Interfaces\IEmailUser $to
     * @return void
     * @throws Exceptions\EmailException
     */
    protected function enableMailOnRemote(Interfaces\IEmailUser $to): void
    {
        $libMailgun = libMailgun::create($this->confKey);
        try {
            $libMailgun->suppressions()->unsubscribes()->delete($this->confTarget, $to->getEmail());
            $libMailgun->suppressions()->bounces()->delete($this->confTarget, $to->getEmail());
        } catch (HttpClientException $ex) {
            throw new Exceptions\EmailException($ex->getMessage(), $ex->getCode(), $ex);
        }
    }
}
