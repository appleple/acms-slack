<?php

namespace Acms\Plugins\Slack;

use ACMS_App;
use Acms\Services\Facades\Storage;
use Acms\Services\Common\HookFactory;
use Acms\Services\Common\InjectTemplate;

class ServiceProvider extends ACMS_APP
{
    /**
     * @var string
     */
    public $version = '1.0.0';

    /**
     * @var string
     */
    public $name = 'Slack';

    /**
     * @var string
     */
    public $author = 'com.appleple';

    /**
     * @var bool
     */
    public $module = false;

    /**
     * @var bool|string
     */
    public $menu = false;

    /**
     * @var string
     */
    public $desc = 'Slack API と連携し、フォームの送信内容をSlackで通知するための機能を提供します。';

    /**
     * サービスの初期処理
     */
    public function init()
    {
        require_once dirname(__FILE__) . '/vendor/autoload.php';

        $hook = HookFactory::singleton();
        $hook->attach('SlackHook', new Hook);

        $inect = InjectTemplate::singleton();
        $inect->add('admin-form', '/extension/plugins/Slack/theme/admin/app/slack/form.html');
    }

    /**
     * インストールする前の環境チェック処理
     *
     * @return bool
     */
    public function checkRequirements()
    {
        return true;
    }

    /**
     * インストールするときの処理
     * データベーステーブルの初期化など
     *
     * @return void
     */
    public function install()
    {

    }

    /**
     * アンインストールするときの処理
     * データベーステーブルの始末など
     *
     * @return void
     */
    public function uninstall()
    {

    }

    /**
     * アップデートするときの処理
     *
     * @return bool
     */
    public function update()
    {
        return true;
    }

    /**
     * 有効化するときの処理
     *
     * @return bool
     */
    public function activate()
    {
        return true;
    }

    /**
     * 無効化するときの処理
     *
     * @return bool
     */
    public function deactivate()
    {
        return true;
    }
}