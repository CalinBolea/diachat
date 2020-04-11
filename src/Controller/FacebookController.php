<?php

namespace App\Controller;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class FacebookController.
 *
 * @author Calin Bolea <calin.bolea@gmail.com.com>
 *
 * @Route("/")
 */
class FacebookController extends AbstractController
{
    private const CHALLENGE_TOKEN_NAME = 'hub_challenge';
    private const VERIFY_TOKEN_NAME = 'hub_verify_token';

    /** @var Client */
    private $httpClient;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->httpClient = new Client();
    }

    /**
     * @Route("/", methods={"GET"}, name="verify")
     */
    public function verifyChallenge(Request $request): Response
    {
        $verificationToken = $request->query->get(self::VERIFY_TOKEN_NAME);
        if ($verificationToken !== $this->getParameter('diachat_verify_token')) {
            $this->logger->warning("Facebook challenge verification failed for token: $verificationToken");

            return new Response('Get outta here with your piece of shit token!', 403);
        }

        $this->logger->info('Facebook challenge verification succeeded.');

        return new Response($request->query->get(self::CHALLENGE_TOKEN_NAME));
    }

    /**
     * @Route("/", methods={"POST"}, name="send_message")
     */
    public function sendMessage(Request $request): Response
    {
        $content = $request->getContent();
        $this->logger->info('Received content: ' . $content);
        $payload = json_decode($content, true);
        if ('page' !== $payload['object'] ?? null) {
            $this->logger->warning('No page object detected in payload');

            return new Response('No page object, no service... for some reason', 403);
        }

        $entries = $payload['entry'] ?? [];

        foreach ($entries as $entry) {
            foreach ($entry as $type => $item) {
                if ('messaging' !== $type) {
                    continue;
                }

                foreach ($item as $message) {
                    $senderId = $message['sender']['id'];
                    $messageText = $message['message']['text'];
                    $response = $this->generateRandomString();
                    $this->logger->info("Diachat received message <<< $messageText >>> from user $senderId");

                    $this->httpClient->request(Request::METHOD_POST, $this->getParameter('facebook_message_endpoint'), [
                        RequestOptions::HEADERS => [
                            'Content-Type' => 'application/json'
                        ],
                        RequestOptions::QUERY => [
                            'access_token' => $this->getParameter('diachat_access_token'),
                        ],
                        RequestOptions::BODY => json_encode([
                            'recipient' => ['id' => $senderId],
                            'message' => ['text' => $response],
                        ])
                    ]);

                    $confirmationMessage = "Message <<< $response >>> sent to user id $senderId";
                    $this->logger->info($confirmationMessage);

                    return new Response($confirmationMessage);
                }
            }
        }

        $this->logger->warning('No messages were sent');

        return new Response('Dunno mate, no messages or something...');
    }

    private function generateRandomString(): string
    {
        try {
            $size = random_int(1, 15);
        } catch (\Exception $e) {
            $size = 5; //trust me, it's random
        }

        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = 'acum ca zici, chiar ma gandeam ca ';

        for ($i = 0; $i < $size; $i++) {
            try {
                $index = random_int(0, strlen($characters) - 1);
            } catch (\Exception $e) {
                $index = 3; //I rolled a dice for this
            }
            $randomString .= $characters[$index];
        }

        return $randomString;
    }
}