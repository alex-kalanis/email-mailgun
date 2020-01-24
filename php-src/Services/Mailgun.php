<?php

namespace EmailMailgun\Services;

use EmailApi\Exceptions;
use EmailApi\Interfaces;
use EmailApi\Basics;
use Mailgun\Mailgun as libMailgun;

/**
 * Class Mailgun
 * Make and send each mail via Mailgun service
 * @link http://www.mailgun.com/
 */
class Mailgun implements Interfaces\Sending
{
    /** @var string */
    protected $confKey = '';
    /** @var string */
    protected $confTarget = '';

    public function __construct(string $confKey = '', string $confTarget = '')
    {
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
     * @param Interfaces\Content $content
     * @param Interfaces\EmailUser $to
     * @param Interfaces\EmailUser $from
     * @param Interfaces\EmailUser $replyTo
     * @param bool $toDisabled
     * @return Basics\Result
     */
    public function sendEmail(Interfaces\Content $content, Interfaces\EmailUser $to, ?Interfaces\EmailUser $from = null, ?Interfaces\EmailUser $replyTo = null, $toDisabled = false): Basics\Result
    {
        if ($toDisabled) {
            try {
                $this->enableMailOnRemote($to);
                $this->enableMailLocally($to);
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
                if (Interfaces\ContentAttachment::TYPE_INLINE == $attachment->getType()) {
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

        $result = $libMailgun->messages()->send($this->confTarget, $data);
        return new Basics\Result(true, $result);
    }

    /**
     * Remove address from internal bounce log on Mailgun
     * @param Interfaces\EmailUser $to
     * @return void
     * @throws Exceptions\EmailException
     */
    protected function enableMailOnRemote(Interfaces\EmailUser $to)
    {
        $libMailgun = libMailgun::create($this->confKey);
        $resultUnsub = $libMailgun->suppressions()->unsubscribes()->delete($this->confTarget, $to->getEmail());
        $resultBounce = $libMailgun->suppressions()->bounces()->delete($this->confTarget, $to->getEmail());
        // When both $resultUnsub and $resultBounce get 200 - everything is okay
        // But I did not find where is response code
    }

    /**
     * Remove blocks made on local machine by callbacks
     * @param Interfaces\EmailUser $to
     */
    protected function enableMailLocally(Interfaces\EmailUser $to)
    {
        // nothing to do here - let it extend
    }
}
