<?php

namespace Acms\Plugins\Slack;

use ACMS_POST_Form_Submit;

class Hook
{
    /**
     * POSTモジュール処理前
     * $thisModuleのプロパティを参照・操作するなど
     *
     * @param \ACMS_POST $thisModule
     */
    public function afterPostFire($thisModule)
    {
        if (!($thisModule instanceof ACMS_POST_Form_Submit)) {
            return;
        }
        if (!$thisModule->Post->isValidAll()) {
            return;
        }
        $step = $thisModule->Post->get('error');
        if (empty($step)) {
            $step = $thisModule->Get->get('step');
        }
        $step = $thisModule->Post->get('step', $step);
        if (in_array($step, array('forbidden', 'repeated'))) {
            return;
        }
        $formCode = $thisModule->Post->get('id');
        try {
            $engine = new Engine($formCode, $thisModule);
            $engine->send();
        } catch (\Exception $e) {
            userErrorLog('ACMS Warning: Slack plugin, ' . $e->getMessage());
        }
    }
}
