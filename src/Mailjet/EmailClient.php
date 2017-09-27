<?php

namespace AppBundle\Mailjet;

use AppBundle\Mailer\AbstractEmailClient;
use AppBundle\Mailer\EmailClientInterface;
use AppBundle\Mailjet\Exception\MailjetException;
use Symfony\Component\HttpFoundation\Response;

class EmailClient extends AbstractEmailClient implements EmailClientInterface
{
    public function sendEmail(string $email): string
    {
        $response = $this->request('POST', 'send', [
            'body' => $email,
        ]);

        if (Response::HTTP_OK !== $response->getStatusCode()) {
            throw new MailjetException('Unable to send email to recipients.');
        }

        return (string) $response->getBody();
    }
}
