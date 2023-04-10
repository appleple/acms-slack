<?php

namespace Acms\Plugins\Slack;

use Field;
use Field_Validation;
use lygav\slackbot\SlackBot;

class Engine
{
    /**
     * @var \Field
     */
    protected $formField;

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
        $message = $this->config->get('slack_form_message');
        $channel = $this->config->get('slack_form_channel');
        $from = $this->config->get('slack_form_from');

        $tpl = '<!-- BEGIN_MODULE Form --><!-- BEGIN step#result -->'.$message.'<!-- END step#result --><!-- END_MODULE Form -->';
        $text = build(setGlobalVars($tpl), Field_Validation::singleton('post'));
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
