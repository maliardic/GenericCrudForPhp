<?php
/**
 * Created by PhpStorm.
 * User: mali
 * Date: 24.01.2019
 * Time: 11:42
 */

//require_once APP_CORE_DIR . 'db.php';
require_once APP_CORE_DIR . 'Config.php';
require_once APP_CORE_DIR . 'Language.php';
require_once APP_CORE_DIR . 'Auth.class.php';

class GenericModel
{
    public static function insertRecord($tableName, $populateCallback, $msg)
    {
        R::begin();
        try {
            $bean = R::dispense($tableName);
            $user_id = $_SESSION['user_id'] ?? null;
            if (!$user_id) throw new Exception('Oturum bilgisi eksik!');

            if (is_callable($populateCallback)) call_user_func($populateCallback, $bean);

            $bean->enabled = 'y';
            $bean->deleted = 'n';
            $bean->create_date = date("Y-m-d H:i:s");
            $bean->create_user = $user_id;

            $id = R::store($bean);
            R::commit();

            MLog::insertLog($msg['insert'], strtoupper($tableName) . '_NEW');
            MFuncs::printJSONSuccess($msg['insert']);
            return $id;

        } catch (Exception $e) {
            R::rollback();
            MLog::insertLog('HATA: Kayıt eklenemedi - ' . $e->getMessage(), strtoupper($tableName) . '_INSERT_ERROR');
            MFuncs::printJSONError("Hata: {$msg['mysql_error']}. Sistem mesajı: " . $e->getMessage());
            return false;
        }
    }

    public static function updateRecord($tableName, $id, $populateCallback, $msg)
    {
        R::begin();
        try {
            $bean = R::load($tableName, $id);
            if (!$bean->id) throw new Exception('Kayıt bulunamadı!');

            if (is_callable($populateCallback)) call_user_func($populateCallback, $bean);

            $bean->update_date = date("Y-m-d H:i:s");
            $bean->update_user = $_SESSION['user_id'] ?? null;

            R::store($bean);
            R::commit();

            MLog::insertLog($msg['update'], strtoupper($tableName) . '_UPDATE');
            MFuncs::printJSONSuccess($msg['update']);
            return true;

        } catch (Exception $e) {
            R::rollback();
            MLog::insertLog('HATA: Güncelleme başarısız - ' . $e->getMessage(), strtoupper($tableName) . '_UPDATE_ERROR');
            MFuncs::printJSONError("Hata: {$msg['mysql_error']}. Sistem mesajı: " . $e->getMessage());
            return false;
        }
    }

    public static function getById($tableName, $id, $checkSuperUser = false)
    {
        $table = MConfig::getTable($tableName);
        $sql = "SELECT * FROM `$table` WHERE `id` = '$id' AND `deleted` = 'n' AND `enabled` = 'y'";

        if ($checkSuperUser) {
            if (!$_SESSION['formsuperuser']) {
                $user_id = $_SESSION['user_id'];
                $sql .= " AND `create_user` = '$user_id'";
            }
        }

        $row = R::getRow($sql);
        return $row ?: null;
    }

    public static function getList($tableName, $checkSuperUser = false, $filters = ['enabled' => 'y', 'deleted' => 'n'], $order = 'id DESC')
    {
        $table = MConfig::getTable($tableName);
        $sql = "SELECT * FROM `$table` WHERE 1=1";

        if ($checkSuperUser) {
            if (!$_SESSION['formsuperuser']) {
                $user_id = $_SESSION['user_id'];
                $sql .= " AND `create_user` = '$user_id'";
            }
        }

        foreach ($filters as $field => $value) {
            $sql .= " AND `$field` = '$value'";
        }

        if (!empty($order)) {
            $sql .= " ORDER BY $order";
        }

        return R::getAll($sql);
    }

    public static function deleteRecord($tableName, $id, $msg, $checkSuperUser = false)
    {
        $table = MConfig::getTable($tableName);
        $sql = "UPDATE `$table` SET `deleted` = 'y' WHERE `id` = '$id'";

        if ($checkSuperUser) {
            if (!$_SESSION['formsuperuser']) {
                $user_id = $_SESSION['user_id'];
                $sql .= " AND `create_user` = '$user_id'";
            }
        }

        R::exec($sql);
        MLog::insertLog($msg['delete'], strtoupper($tableName) . '_DELETE');
        MFuncs::printJSONSuccess($msg['delete']);
        return true;
    }

    public static function saveRecord($tableName, $populateCallback, $msg)
    {
        R::begin();
        try {
            $id = MFuncs::getPostValue('id', 'int');
            if ($id > 0) {
                $bean = R::load($tableName, $id);
                if (!$bean->id) throw new Exception('Kayıt bulunamadı!');
                $bean->update_date = date("Y-m-d H:i:s");
                $bean->update_user = $_SESSION['user_id'] ?? null;
                $logKey = '_UPDATE';
                $successMsg = $msg['update'];
            } else {
                $bean = R::dispense($tableName);
                $bean->enabled = 'y';
                $bean->deleted = 'n';
                $bean->create_date = date("Y-m-d H:i:s");
                $bean->create_user = $_SESSION['user_id'] ?? null;
                $logKey = '_NEW';
                $successMsg = $msg['insert'];
            }

            if (is_callable($populateCallback)) call_user_func($populateCallback, $bean);

            R::store($bean);
            R::commit();

            MLog::insertLog($successMsg, strtoupper($tableName) . $logKey);
            MFuncs::printJSONSuccess($successMsg);
            return $bean->id;

        } catch (Exception $e) {
            R::rollback();
            MLog::insertLog('HATA: Kaydetme başarısız - ' . $e->getMessage(), strtoupper($tableName) . '_SAVE_ERROR');
            MFuncs::printJSONError("Hata: {$msg['mysql_error']}. Sistem mesajı: " . $e->getMessage());
            return false;
        }
    }

    public static function populateBeanGeneric(&$bean, $fieldList = [])
    {
        foreach ($fieldList as $field => $type) {
            if ($type === 'datetime') {
                $date = MFuncs::getPostValue($field, 'string');
                $bean->$field = MFuncs::format_date($date, 'mysql-datetime');
            } else {
                $bean->$field = MFuncs::getPostValue($field, $type);
            }
        }
        $bean->date_time = date('Y-m-d H:i:s');
    }
    public static function getListCount($tableName, $checkSuperUser = false, $filters = ['enabled' => 'y', 'deleted' => 'n'])
    {
        $table = MConfig::getTable($tableName);
        $sql = "SELECT COUNT(*) as count FROM `$table` WHERE 1=1";

        if ($checkSuperUser) {
            if (!$_SESSION['formsuperuser']) {
                $user_id = $_SESSION['user_id'];
                $sql .= " AND `create_user` = '$user_id'";
            }
        }

        foreach ($filters as $field => $value) {
            $sql .= " AND `$field` = '$value'";
        }

        $row = R::getRow($sql);
        return $row ? (int)$row['count'] : 0;
    }

    function generateDynamicForm($fieldList, $row = []) {
        echo '<form name="form_panel" action="" id="form_panel" class="form_cont">';
        echo '<input type="text" name="id" id="id" class="hide"  value="' . intval($row['id'] ?? 0) . '" />';
        echo '<input type="text" name="type" id="type" class="hide"  value="' . htmlspecialchars($row['type'] ?? '') . '" />';
        echo '<input type="text" name="parent_id" id="parent_id" class="hide3"  value="' . htmlspecialchars($row['parent_id'] ?? '') . '" />';
        echo '<input type="text" name="form_submit" id="form_submit" class="hide"  value="" />';

        foreach ($fieldList as $field => $settings) {
            $label = $settings['label'] ?? ucfirst($field);
            $type = $settings['type'] ?? 'input';
            $options = $settings['options'] ?? null;
            $value = $row[$field] ?? '';
            $class = $settings['class'] ?? '';
            $disabled = $settings['disabled'] ?? '';

            // MFuncs form_print ile basıyoruz
            MFuncs::form_print('', '', $field, $label, $type, $value, $options, $disabled, $class);
        }

        echo '</form>';
    }

}


?>
