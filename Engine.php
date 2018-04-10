<?php

namespace Acms\Plugins\Slack;

use DB;
use SQL;
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
     */
    public function __construct($code)
    {
        $field = $this->loadFrom($code);
        if (empty($field)) {
            throw new \RuntimeException('Not Found Form.');
        }
        $this->formField = $field;
        $this->config = $field->getChild('mail');
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

    /**
     * @param string $code
     * @return bool|Field
     */
    protected function loadFrom($code)
    {
        $DB = DB::singleton(dsn());
        $SQL = SQL::newSelect('form');
        $SQL->addWhereOpr('form_code', $code);
        $row = $DB->query($SQL->get(dsn()), 'row');

        if (!$row) {
            return false;
        }
        $Form = new Field();
        $Form->set('code', $row['form_code']);
        $Form->set('name', $row['form_name']);
        $Form->set('scope', $row['form_scope']);
        $Form->set('log', $row['form_log']);
        $Form->overload(unserialize($row['form_data']), true);

        return $Form;
    }
}