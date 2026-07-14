<?php

namespace Acms\Plugins\Slack;

/**
 * Slack Incoming Webhook への最小クライアント。
 *
 * 旧 lygav/php-slackbot（メンテ停止・PHP 8.5 で非推奨の curl_close を無ガードで呼ぶ）を
 * 置き換えるための自前実装。Engine が使う text()/from()/toChannel()/send() のみを提供する。
 * PHP 5.3 でも動くよう array() 構文・スカラー型宣言なしで書いている。
 */
class SlackWebhookClient
{
    /**
     * @var string
     */
    private $webhookUrl;

    /**
     * @var string
     */
    private $text = '';

    /**
     * text 以外に payload へ載せる項目（username / channel など）。
     * @var array
     */
    private $options = array();

    /**
     * @param string $webhookUrl
     */
    public function __construct($webhookUrl)
    {
        $this->webhookUrl = $webhookUrl;
    }

    /**
     * @param string $text
     * @return $this
     */
    public function text($text)
    {
        $this->text = (string) $text;
        return $this;
    }

    /**
     * 送信者名（username）を指定する。
     * @param string $name
     * @return $this
     */
    public function from($name)
    {
        $this->options['username'] = $name;
        return $this;
    }

    /**
     * 投稿先チャンネルを指定する。先頭に # が無ければ付与する。
     * @param string $name
     * @return $this
     */
    public function toChannel($name)
    {
        $this->options['channel'] = $this->normalizeTarget($name, '#');
        return $this;
    }

    /**
     * 投稿先グループを指定する（Slack 上はチャンネルと同じ扱い）。
     * @param string $name
     * @return $this
     */
    public function toGroup($name)
    {
        return $this->toChannel($name);
    }

    /**
     * DM 送信先ユーザーを指定する。先頭に @ が無ければ付与する。
     * @param string $name
     * @return $this
     */
    public function toPerson($name)
    {
        $this->options['channel'] = $this->normalizeTarget($name, '@');
        return $this;
    }

    /**
     * Incoming Webhook へ POST する。成功時は "ok" を返す。
     * @return string
     * @throws \RuntimeException
     */
    public function send()
    {
        $payload = array_merge(array('text' => $this->text), $this->options);
        // Incoming Webhook は payload= に URL エンコードした JSON を載せる旧来仕様。
        $body = http_build_query(array('payload' => json_encode($payload)));

        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $this->webhookUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
        ));
        $result = curl_exec($ch);
        $error = curl_error($ch);
        // PHP 7.x 以前は明示的な close が必要。8.0+ では no-op（8.5 で非推奨）のため呼ばない。
        if (PHP_VERSION_ID < 80000) {
            // phpcs:ignore Generic.PHP.DeprecatedFunctions.Deprecated, PHPCompatibility.FunctionUse.RemovedFunctions.curl_closeDeprecated
            curl_close($ch);
        }

        if ($result === false) {
            throw new \RuntimeException('Slack への送信に失敗しました: ' . $error);
        }
        if ($result !== 'ok') {
            throw new \RuntimeException('Slack への送信に失敗しました: ' . $result);
        }
        return $result;
    }

    /**
     * 先頭に指定の記号（# / @）が無ければ付与する。空文字はそのまま返す。
     * @param string $name
     * @param string $prefix
     * @return string
     */
    private function normalizeTarget($name, $prefix)
    {
        $name = (string) $name;
        if ($name === '') {
            return $name;
        }
        $first = substr($name, 0, 1);
        if ($first === '#' || $first === '@') {
            return $name;
        }
        return $prefix . $name;
    }
}
