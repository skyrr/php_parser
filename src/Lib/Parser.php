<?php

namespace Lib;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Yangqi\Htmldom\Htmldom;

class Parser
{
    const BASE_URL = 'https://www.wewewe.ua';
    const USER_AGENT = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/53.0.2785.92 Safari/537.36';
    const LOGIN_URL = self::BASE_URL . '/employer/login/';
    const RESUME_URL = self::BASE_URL . '/resumes/?ss';
    const OPEN_DATA_URL = self::BASE_URL . '/_data/_ajax/resumes_selection.php';

    private $email;
    private $password;

    /**
     * @var $client Client
     */
    private $client;

    /**
     * @var $cookies CookieJar[]
     */
    private $cookies;
    private $secret;

    public function __construct($email, $password)
    {
        $this->email = $email;
        $this->password = $password;
    }

    public function auth()
    {
        $this->client->get(self::LOGIN_URL);
        $response = $this->client->get(self::LOGIN_URL . '?check_cookie=1');

        $dom = new Htmldom($response->getBody());
        $this->secret = $dom->find('input[name=secret]', 0)->value;

        sleep(3);
        $this->client->post(self::LOGIN_URL, [
            'form_params' => [
                'remember'      => 'on',
                'secret'        => $this->secret,
                'login' => [
                    1 => $this->email
                ],
                'password' => [
                    1 => $this->password
                ]
            ],
        ]);

        return $this;
    }

    public function parseAllResume()
    {
        sleep(3);
        $html = new Htmldom(self::RESUME_URL);

        foreach($html->find('div.resume-link') as $element){
            $href = $element->find('a', 0)->href;
            $this->parseResume($href);
            break;
        }
    }

    public function parseResume($uri)
    {
        $user_id = preg_replace("/^\/resumes\/(\d+)\/$/", "$1", $uri);

        sleep(3); // pause
        $response = $this->client->post(self::OPEN_DATA_URL, [
            'form_params' => [
                'func' => 'showResumeContacts',
                'id' => $user_id
            ]
        ]);

        $response = json_decode($response->getBody()->getContents());
        if ($response->status <> 'ok') {
            return;
        }

        $phone = $response->contact->phone_prim;
        $email = $response->contact->email;

        var_dump($phone, $email);
    }//

    public function createClient()
    {
        $handler_stack = new HandlerStack();
        $handler_stack->setHandler(new CurlHandler());
        $handler_stack->push(Middleware::mapRequest(function (RequestInterface $request) {
            $request = $request->withHeader('User-Agent', self::USER_AGENT);
            if ($this->cookies) {
                $jar = new CookieJar(true, $this->cookies);
                $request = $jar->withCookieHeader($request);
            }

            return $request;
        }));
        $handler_stack->push(Middleware::mapResponse(function (ResponseInterface $response) {

            $set_cookie = $response->getHeader('Set-Cookie');
            if ($set_cookie) {
                foreach ($set_cookie as $item) {
                    $set_cookie = SetCookie::fromString($item);
                    $set_cookie->setDomain('www.wewewe.ua');
                    $this->cookies[] = $set_cookie;
                }
            }

            return $response;
        }));

        $this->client = new Client(['cookies' => true, 'handler' => $handler_stack]);
        return $this;
    }
}
