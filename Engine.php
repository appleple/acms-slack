<?php

namespace Acms\Plugins\Slack;

use Field;
use Common;
use lygav\slackbot\SlackBot;

class Engine
{
    /**
     * @var \ACMS_POST
     */
    protected $module;

    /**
     * @var \Field
     */
    protected $config;

    /**
     * Engine constructor.
     * @param string $code
     * @param \ACMS_POST
     */
    public function __construct($code, $module)
    {
        $info = $module->loadForm($code);
        if (empty($info)) {
            throw new \RuntimeException('Not Found Form.');
        }
        $this->config = $info['data']->getChild('mail');
    }

    /**
     * Send
     */
    public function send()
    {
        $hook_url = $this->config->get('slack_incoming_hook_url');
        if (empty($hook_url)) {
            throw new \RuntimeException('Empty hook url.');
        }
        $bot = new Slackbot($this->config->get('slack_incoming_hook_url'));
        $messageTpl = $this->config->get('slack_form_message');
        $channel = $this->config->get('slack_form_channel');
        $from = $this->config->get('slack_form_from');

        $text = Common::getMailTxtFromTxt($messageTpl, $this->module->Post->getChild('field'));;
        $slack = $bot->text($text);
        if ($channel) {
            $slack->toChannel($channel);
        }
        if ($from) {
            $slack->from($from);
        }
        $slack->send();
    }
}
