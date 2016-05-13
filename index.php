<?php

$verify_token = ""; // Verify token
$token = ""; // Page token
$config = []; // config

if (file_exists(__DIR__.'/config.php')) {
    $config = include __DIR__.'/config.php';
    $verify_token = $config['verify_token'];
    $token = $config['token'];
}

require_once(dirname(__FILE__) . '/vendor/autoload.php');

use PicoFeed\Reader\Reader;
use pimax\FbBotApp;
use pimax\Messages\Message;
use pimax\Messages\MessageButton;
use pimax\Messages\StructuredMessage;
use pimax\Messages\MessageElement;

$bot = new FbBotApp($token);

if (!empty($_REQUEST['hub_mode']) && $_REQUEST['hub_mode'] == 'subscribe' && $_REQUEST['hub_verify_token'] == $verify_token)
{
    // Webhook setup request
    echo $_REQUEST['hub_challenge'];
} else {

    $data = json_decode(file_get_contents("php://input"), true);
    if (!empty($data['entry'][0]['messaging']))
    {
        foreach ($data['entry'][0]['messaging'] as $message)
        {
            if (!empty($data['entry'][0])) {

                if (!empty($data['entry'][0]['messaging']))
                {
                    foreach ($data['entry'][0]['messaging'] as $message)
                    {
                        if (!empty($message['delivery'])) {
                            continue;
                        }

                        $command = "";

                        if (!empty($message['message'])) {
                            $command = $message['message']['text'];
                        } else if (!empty($message['postback'])) {
                            $command = $message['postback']['payload'];
                        }

                        if (!empty($config['feeds'][$command]))
                        {
                            getFeed($config['feeds'][$command], $bot, $message);
                        } else {
                            sendHelpMessage($bot, $message);
                        }
                    }
                }
            }
        }
    }
}

/**
 * Send Help Message
 *
 * @param $bot Bot instance
 * @param array $message Received message
 * @return bool
 */
function sendHelpMessage($bot, $message)
{
    $bot->send(new Message($message['sender']['id'], 'Hello! Please choose the category:'));
    $bot->send(new StructuredMessage($message['sender']['id'],
        StructuredMessage::TYPE_GENERIC,
        [
            'elements' => [
                new MessageElement('All jobs', 'Projects in all categories', [
                    new MessageButton(MessageButton::TYPE_POSTBACK, 'Show projects', 'All jobs')
                ]),

                new MessageElement('Development', 'Projects for developers', [
                    new MessageButton(MessageButton::TYPE_POSTBACK, 'Web Development'),
                    new MessageButton(MessageButton::TYPE_POSTBACK, 'Software Development & IT'),
                    new MessageButton(MessageButton::TYPE_POSTBACK, 'Mobile Application'),
                ]),

                new MessageElement('Writers', 'Projects for writers and translators', [
                    new MessageButton(MessageButton::TYPE_POSTBACK, 'Writing'),
                    new MessageButton(MessageButton::TYPE_POSTBACK, 'Translation & Languages')
                ]),

                new MessageElement('Design & Multimedia', 'Design & Multimedia projects', [
                    new MessageButton(MessageButton::TYPE_POSTBACK, 'Show Projects', 'Design & Multimedia'),
                ]),

                new MessageElement('Host & Server Management', 'Host & Server Management projects', [
                    new MessageButton(MessageButton::TYPE_POSTBACK, 'Show projects', 'Host & Server Management')
                ]),

                new MessageElement('Marketing', 'Marketing projects', [
                    new MessageButton(MessageButton::TYPE_POSTBACK, 'Show projects', 'Marketing')
                ]),

                new MessageElement('Business Services', 'Business Services projects', [
                    new MessageButton(MessageButton::TYPE_POSTBACK, 'Show projects', 'Business Services'),
                ]),


            ]
        ]
    ));

    return true;
}

/**
 * Get Feed Data
 *
 * @param $url Feed url
 * @param $bot Bot instance
 * @param $message Received message
 * @return bool
 */
function getFeed($url, $bot, $message)
{
    try {
        $reader = new Reader;
        $resource = $reader->download($url);

        $parser = $reader->getParser(
            $resource->getUrl(),
            $resource->getContent(),
            $resource->getEncoding()
        );

        $feed = $parser->execute();
        $items = array_reverse($feed->getItems());

        if (count($items)) {
            foreach ($items as $itm)
            {
                $url = $itm->getUrl();
                $message_text = substr(strip_tags($itm->getContent()), 0, 80);

                $bot->send(new StructuredMessage($message['sender']['id'],
                    StructuredMessage::TYPE_GENERIC,
                    [
                        'elements' => [
                            new MessageElement($itm->getTitle(), $message_text, '', [
                                new MessageButton(MessageButton::TYPE_WEB, 'Read more', $url)
                            ]),

                        ]
                    ]
                ));
            }

        } else {
            $bot->send(new Message($message['sender']['id'], 'Not found a new projects in this section.'));
        }
    }
    catch (Exception $e) {
        writeToLog($e->getMessage(), 'Exception');
    }

    return true;
}

/**
 * Log
 *
 * @param mixed $data Data
 * @param string $title Title
 * @return bool
 */
function writeToLog($data, $title = '')
{
    $log = "\n------------------------\n";
    $log .= date("Y.m.d G:i:s") . "\n";
    $log .= (strlen($title) > 0 ? $title : 'DEBUG') . "\n";
    $log .= print_r($data, 1);
    $log .= "\n------------------------\n";

    file_put_contents(__DIR__ . '/imbot.log', $log, FILE_APPEND);

    return true;
}