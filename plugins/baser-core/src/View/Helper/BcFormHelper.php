<?php
/**
 * baserCMS :  Based Website Development Project <https://basercms.net>
 * Copyright (c) NPO baser foundation <https://baserfoundation.org/>
 *
 * @copyright     Copyright (c) NPO baser foundation
 * @link          https://basercms.net baserCMS Project
 * @since         5.0.0
 * @license       https://basercms.net/license/index.html MIT License
 */

namespace BaserCore\View\Helper;

use BaserCore\Utility\BcContainerTrait;
use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;
use Cake\View\Form\EntityContext;
use Cake\View\Helper\FormHelper;
use Cake\Datasource\EntityInterface;
use BaserCore\Event\BcEventDispatcherTrait;
use BaserCore\Annotation\Note;
use BaserCore\Annotation\NoTodo;
use BaserCore\Annotation\Checked;
use BaserCore\Annotation\UnitTest;

/**
 * FormHelper 拡張クラス
 *
 * @property BcHtmlHelper $BcHtml
 * @property BcUploadHelper $BcUpload
 * @property BcCkeditorHelper $BcCkeditor
 */
class BcFormHelper extends FormHelper
{
    /**
     * Trait
     */
    use BcEventDispatcherTrait;
    use BcContainerTrait;

    /**
     * Other helpers used by FormHelper
     *
     * @var array
     */
    public $helpers = [
        'Url',
        'Js',
        'Html',
        'BaserCore.BcHtml',
        'BaserCore.BcTime',
        'BaserCore.BcText',
        'BaserCore.BcUpload',
        'BaserCore.BcCkeditor'
    ];

// CUSTOMIZE ADD 2014/07/02 ryuring
// >>>
    /**
     * フォームID
     *
     * @var string
     */
    private $formId = null;
// <<<

    /**
     * フォームの最後のフィールドの後に発動する前提としてイベントを発動する
     *
     * ### 発動側
     * フォームの</table>の直前に記述して利用する
     *
     * ### コールバック処理
     * プラグインのコールバック処理で CakeEvent::data['fields'] に
     * 配列で行データを追加する事でフォームの最後に行を追加する事ができる。
     *
     * ### イベント名
     * コントローラー名.Form.afterForm Or コントローラー名.Form.afterOptionForm
     *
     * ### 行データのキー（配列）
     * - title：見出欄
     * - input：入力欄
     *
     * ### 行データの追加例
     *  $View = $event->subject();    // $event は、CakeEvent
     *  $input = $View->BcForm->input('Page.add_field', ['type' => 'input']);
     *  $event->setData('fields', [
     *      [
     *          'title'    => '追加フィールド',
     *          'input'    => $input
     *      ]
     *  ]);
     *
     * @param string $type フォームのタイプ タイプごとにイベントの登録ができる
     * @return string 行データ
     * @checked
     * @note(value="フォームの最後のフィールドの後に発動するイベント")
     */
    public function dispatchAfterForm($type = ''): string
    {
        if ($type) {
            $type = Inflector::camelize($type);
        }
        $event = $this->dispatchLayerEvent('after' . $type . 'Form', ['fields' => [], 'id' => $this->formId], ['class' => 'Form', 'plugin' => '']);
        $out = '';
        if ($event !== false) {
            if (!empty($event->getData('fields'))) {
                foreach($event->getData('fields') as $field) {
                    $out .= "<tr>";
                    $out .= "<th class=\"bca-form-table__label\">" . $field['title'] . "</th>\n";
                    $out .= "<td class=\"bca-form-table__input\">" . $field['input'] . "</td>\n";
                    $out .= "</tr>";
                }
            }
        }
        return $out;
    }

    /**
     * コントロールソースを取得する
     * Model側でメソッドを用意しておく必要がある
     *
     * @param string $field フィールド名
     * @param array $options
     * @return array|false コントロールソース
     * @checked
     * @noTodo
     * @unitTest
     */
    public function getControlSource($field, $options = [])
    {
        $count = preg_match_all('/\./is', $field, $matches);
        $plugin = $modelName = null;
        if ($count === 1) {
            [$modelName, $field] = explode('.', $field);
            $plugin = $this->_View->getPlugin();
        } elseif ($count === 2) {
            [$plugin, $modelName, $field] = explode('.', $field);
        }
        if (!$modelName) return false;
        $serviceName = (($plugin)?: 'App') . '\\Service\\' . $modelName . 'ServiceInterface';
        $modelName = (($plugin)? $plugin . '.' : '') . $modelName;
        if (method_exists($serviceName, 'getControlSource')) {
            return $this->getService($serviceName)->getControlSource($field, $options);
        }
        if (empty($modelName)) return false;
        $model = TableRegistry::getTableLocator()->get($modelName);
        if ($model && method_exists($model, 'getControlSource')) {
            return $model->getControlSource($field, $options);
        } else {
            return false;
        }
    }

    /**
     * カレンダーピッカー
     *
     * jquery-ui-1系 必須
     *
     * @param string フィールド文字列
     * @param array オプション
     * @return string html
     * @checked
     * @noTodo
     * @unitTest
     */
    public function datePicker($fieldName, $options = [])
    {
        $options = array_merge([
            'autocomplete' => 'off',
            'id' => $this->_domId($fieldName),
            'value' => $this->context()->val($fieldName)
        ], $options);
        if ($options['value']) {
            [$options['value'],] = explode(" ", str_replace('-', '/', $options['value']));
        }
        unset($options['type']);
        $input = $this->text($fieldName, $options);
        $script = <<< SCRIPT_END
<script>
jQuery(function($){
	$("#{$options['id']}").datepicker();
});
</script>
SCRIPT_END;
        return $input . "\n" . $script;
    }

    /**
     * カレンダピッカーとタイムピッカー
     *
     * jquery.timepicker.js 必須
     *
     * @param string $fieldName
     * @param array $options
     * @return string
     * @checked
     * @noTodo
     * @unitTest
     */
    public function dateTimePicker($fieldName, $options = [])
    {
        $options = array_merge([
            'div' => ['tag' => 'span'],
            'dateInput' => [],
            'dateDiv' => ['tag' => 'span'],
            'dateLabel' => ['text' => '日付'],
            'timeInput' => [],
            'timeDiv' => ['tag' => 'span'],
            'timeLabel' => ['text' => '時間'],
            'id' => $this->_domId($fieldName),
            'timeStep' => 30
        ], $options);

        $dateOptions = array_merge($options, [
            'type' => 'datepicker',
            'div' => $options['dateDiv'],
            'label' => $options['dateLabel'],
            'autocomplete' => 'off',
            'id' => $options['id'] . '-date',
        ], $options['dateInput']);

        $timeOptions = array_merge($options, [
            'type' => 'text',
            'div' => $options['timeDiv'],
            'label' => $options['timeLabel'],
            'autocomplete' => 'off',
            'size' => 8,
            'maxlength' => 8,
            'escape' => true,
            'id' => $options['id'] . '-time',
            'step' => $options['timeStep'],
        ], $options['timeInput']);

        unset($options['dateDiv'], $options['dateLabel'], $options['timeDiv'], $options['timeLabel'], $options['dateInput'], $options['timeInput'], $options['timeStep']);
        unset($dateOptions['dateDiv'], $dateOptions['dateLabel'], $dateOptions['timeDiv'], $dateOptions['timeLabel'], $dateOptions['dateInput'], $dateOptions['timeInput']);
        unset($timeOptions['dateDiv'], $timeOptions['dateLabel'], $timeOptions['timeDiv'], $timeOptions['timeLabel'], $timeOptions['dateInput'], $timeOptions['timeInput']);

        if (!isset($options['value'])) {
            $value = $this->context()->val($fieldName);
        } else {
            $value = $options['value'];
            unset($options['value']);
        }

        if ($value && $value != '0000-00-00 00:00:00') {
            if (strpos($value, ' ') !== false) {
                [$dateValue, $timeValue] = explode(' ', $value);
            } else {
                $dateValue = $value;
                $timeValue = '00:00:00';
            }
            $dateOptions['value'] = $dateValue;
            $timeOptions['value'] = $timeValue;
        }

		$step = $timeOptions['step'];
		unset($timeOptions['step']);

        $dateDivOptions = $timeDivOptions = $dateLabelOptions = $timeLabelOptions = null;
        if (!empty($dateOptions['div'])) {
            $dateDivOptions = $dateOptions['div'];
            unset($dateOptions['div']);
        }
        if (!empty($timeOptions['div'])) {
            $timeDivOptions = $timeOptions['div'];
            unset($timeOptions['div']);
        }
        if (!empty($dateOptions['label'])) {
            $dateLabelOptions = $dateOptions;
            unset($dateOptions['type'], $dateOptions['label']);
        }
        if (!empty($timeOptions['label'])) {
            $timeLabelOptions = $timeOptions;
            unset($timeOptions['type'], $timeOptions['label']);
        }

        $dateTag = $this->datePicker($fieldName . '_date', $dateOptions);
        if ($dateLabelOptions['label']) {
            $dateTag = $this->_getLabel($fieldName, $dateLabelOptions) . $dateTag;
        }
        if ($dateDivOptions) {
            $tag = 'div';
            if (!empty($dateDivOptions['tag'])) {
                $tag = $dateDivOptions['tag'];
                unset($dateDivOptions['tag']);
            }
            $dateTag = $this->BcHtml->tag($tag, $dateTag, $dateDivOptions);
        }

        $timeTag = $this->text($fieldName . '_time', $timeOptions);
        if ($timeLabelOptions['label']) {
            $timeTag = $this->_getLabel($fieldName, $timeLabelOptions) . $timeTag;
        }
        if ($timeDivOptions) {
            $tag = 'div';
            if (!empty($timeDivOptions['tag'])) {
                $tag = $timeDivOptions['tag'];
                unset($timeDivOptions['tag']);
            }
            $timeTag = $this->BcHtml->tag($tag, $timeTag, $timeDivOptions);
        }
        $hiddenTag = $this->hidden($fieldName, ['value' => $value, 'id' => $options['id']]);
        if ($this->formProtector) {
            $this->unlockField($fieldName);
        }
        $script = <<< SCRIPT_END
<script>
$(function(){
    var id = "{$options['id']}";
    var time = $("#" + id + "-time");
    var date = $("#" + id + "-date");
    time.timepicker({ 'timeFormat': 'H:i', 'step': {$step} });
    date.add(time).change(function(){
        if(date.val() && !time.val()) {
            time.val('00:00');
        }
        var value = date.val().replace(/\//g, '-');
        if(time.val()) {
            value += ' ' + time.val();
        }
        $("#" + id).val(value);
    });
});
</script>
SCRIPT_END;
        return $dateTag . $timeTag . $hiddenTag . $script;
    }

    /**
     * Returns an HTML form element.
     *
     * ### Options:
     *
     * - `type` Form method defaults to autodetecting based on the form context. If
     *   the form context's isCreate() method returns false, a PUT request will be done.
     * - `method` Set the form's method attribute explicitly.
     * - `url` The URL the form submits to. Can be a string or a URL array.
     * - `encoding` Set the accept-charset encoding for the form. Defaults to `Configure::read('App.encoding')`
     * - `enctype` Set the form encoding explicitly. By default `type => file` will set `enctype`
     *   to `multipart/form-data`.
     * - `templates` The templates you want to use for this form. Any templates will be merged on top of
     *   the already loaded templates. This option can either be a filename in /config that contains
     *   the templates you want to load, or an array of templates to use.
     * - `context` Additional options for the context class. For example the EntityContext accepts a 'table'
     *   option that allows you to set the specific Table class the form should be based on.
     * - `idPrefix` Prefix for generated ID attributes.
     * - `valueSources` The sources that values should be read from. See FormHelper::setValueSources()
     * - `templateVars` Provide template variables for the formStart template.
     *
     * @param mixed $context The context for which the form is being defined.
     *   Can be a ContextInterface instance, ORM entity, ORM resultset, or an
     *   array of meta data. You can use `null` to make a context-less form.
     * @param array $options An array of html attributes and options.
     * @return string An formatted opening FORM tag.
     * @link https://book.cakephp.org/4/en/views/helpers/form.html#Cake\View\Helper\FormHelper::
     * @checked
     * @noTodo
     * @unitTest
     */
    public function create($context = null, $options = []): string
    {


        // CUSTOMIZE ADD 2014/07/03 ryuring
        // ブラウザの妥当性のチェックを除外する
        // >>>
        $options = array_merge([
            'novalidate' => true
        ], $options);

        $formId = $this->setId($this->createId($context, $options));

        // EVENT Form.beforeCreate
        $event = $this->dispatchLayerEvent('beforeCreate', [
            'id' => $formId,
            'options' => $options
        ], ['class' => 'Form', 'plugin' => '']);
        if ($event !== false) {
            $options = ($event->getResult() === null || $event->getResult() === true)? $event->getData('options') : $event->getResult();
        }
        // <<<

        // 第１引数の $model が $context に変わった
        // >>>
        $out = parent::create($context, $options);
        // <<<

        // CUSTOMIZE ADD 2014/07/03 ryuring
        // >>>
        // EVENT Form.afterCreate
        $event = $this->dispatchLayerEvent('afterCreate', [
            'id' => $formId,
            'out' => $out
        ], ['class' => 'Form', 'plugin' => '']);
        if ($event !== false) {
            $out = ($event->getResult() === null || $event->getResult() === true)? $event->getData('out') : $event->getResult();
        }

        return $out;
        // <<<

    }

    /**
     * Closes an HTML form, cleans up values set by FormHelper::create(), and writes hidden
     * input fields where appropriate.
     *
     * Resets some parts of the state, shared among multiple FormHelper::create() calls, to defaults.
     *
     * @param array $secureAttributes Secure attributes which will be passed as HTML attributes
     *   into the hidden input elements generated for the Security Component.
     * @return string A closing FORM tag.
     * @link https://book.cakephp.org/4/en/views/helpers/form.html#closing-the-form
     * @checked
     * @noTodo
     * @unitTest
     */
    public function end(array $secureAttributes = []): string
    {
        $formId = $this->getId();
        $this->setId(null);

        // EVENT Form.beforeEnd
        $event = $this->dispatchLayerEvent('beforeEnd', [
            'id' => $formId,
            'secureAttributes' => $secureAttributes
        ], ['class' => 'Form', 'plugin' => '']);
        if ($event !== false) {
            $secureAttributes = ($event->getResult() === null || $event->getResult() === true)? $event->getData('secureAttributes') : $event->getResult();
        }

        $out = parent::end($secureAttributes);

        // EVENT Form.afterEnd
        $event = $this->dispatchLayerEvent('afterEnd', [
            'id' => $formId,
            'out' => $out
        ], ['class' => 'Form', 'plugin' => '']);
        if ($event !== false) {
            $out = ($event->getResult() === null || $event->getResult() === true)? $event->getData('out') : $event->getResult();
        }

        return $out;
    }

    /**
     * Generates a form input element complete with label and wrapper div
     *
     * ### Options
     *
     * See each field type method for more information. Any options that are part of
     * $attributes or $options for the different **type** methods can be included in `$options` for input().i
     * Additionally, any unknown keys that are not in the list below, or part of the selected type's options
     * will be treated as a regular html attribute for the generated input.
     *
     * - `type` - Force the type of widget you want. e.g. `type => 'select'`
     * - `label` - Either a string label, or an array of options for the label. See FormHelper::label().
     * - `div` - Either `false` to disable the div, or an array of options for the div.
     *    See HtmlHelper::div() for more options.
     * - `options` - For widgets that take options e.g. radio, select.
     * - `error` - Control the error message that is produced. Set to `false` to disable any kind of error reporting (field
     *    error and error messages).
     * - `errorMessage` - Boolean to control rendering error messages (field error will still occur).
     * - `empty` - String or boolean to enable empty select box options.
     * - `before` - Content to place before the label + input.
     * - `after` - Content to place after the label + input.
     * - `between` - Content to place between the label + input.
     * - `format` - Format template for element order. Any element that is not in the array, will not be in the output.
     *    - Default input format order: array('before', 'label', 'between', 'input', 'after', 'error')
     *    - Default checkbox format order: array('before', 'input', 'between', 'label', 'after', 'error')
     *    - Hidden input will not be formatted
     *    - Radio buttons cannot have the order of input and label elements controlled with these settings.
     *
     * @param string $fieldName This should be "Modelname.fieldname"
     * @param array $options Each type of input takes different options.
     * @return string Completed form widget.
     * @link https://book.cakephp.org/2.0/en/core-libraries/helpers/form.html#creating-form-elements
     */
    public function input($fieldName, $options = [])
    {

        // CUSTOMIZE ADD 2014/07/03 ryuring
        // >>>
        // EVENT Form.beforeInput
        $event = $this->dispatchLayerEvent('beforeInput', [
            'formId' => $this->__id,
            'data' => $this->getView()->getRequest()->getData(),
            'fieldName' => $fieldName,
            'options' => $options
        ], ['class' => 'Form', 'plugin' => '']);
        if ($event !== false) {
            $options = ($event->getResult() === null || $event->getResult() === true)? $event->getData('options') : $event->getResult();
            if (!$options) {
                $options = [];
            }
        }

        $type = '';
        if (isset($options['type'])) {
            $type = $options['type'];
        }

        if (!isset($options['error'])) {
            $options['error'] = false;
        }

        $class = 'bca-hidden__input';
        $divClass = 'bca-hidden';
        $labelClass = $childDivClass = $label = '';
        switch($type) {
            default :
                if (!isset($options['label'])) {
                    $options['label'] = false;
                }
                break;
            case 'file':
                $class = 'bca-file__input';
                $divClass = 'bca-file';
                if (!isset($options['label'])) {
                    $options['label'] = false;
                }
                $options = array_replace_recursive([
                    'link' => ['class' => 'bca-file__link'],
                    'class' => 'bca-file__input',
                    'div' => ['tag' => 'span', 'class' => 'bca-file'],
                    'deleteSpan' => ['class' => 'bca-file__delete'],
                    'deleteCheckbox' => ['class' => 'bca-file__delete-input'],
                    'deleteLabel' => ['class' => 'bca-file__delete-label'],
                    'figure' => ['class' => 'bca-file__figure'],
                    'img' => ['class' => 'bca-file__img'],
                    'figcaption' => ['class' => 'bca-file__figcaption']
                ], $options);
                break;
            case 'dateTimePicker':
                $divClass = 'bca-datetimepicker';
                $options['label'] = false;
                $options = array_replace_recursive([
                    'dateInput' => ['class' => 'bca-datetimepicker__date-input'],
                    'dateDiv' => ['tag' => 'span', 'class' => 'bca-datetimepicker__date'],
                    'dateLabel' => ['text' => '日付', 'class' => 'bca-datetimepicker__date-label'],
                    'timeInput' => ['class' => 'bca-datetimepicker__time-input'],
                    'timeDiv' => ['tag' => 'span', 'class' => 'bca-datetimepicker__time'],
                    'timeLabel' => ['text' => '時間', 'class' => 'bca-datetimepicker__time-label']
                ], $options);
                break;
            case 'text':
            case 'password':
            case 'datePicker':
                if (!isset($options['label'])) {
                    $options['label'] = false;
                }
                $class = 'bca-textbox__input';
                $divClass = 'bca-textbox';
                $labelClass = 'bca-textbox__label';
                break;
            case 'textarea':
                if (!isset($options['label'])) {
                    $options['label'] = false;
                }
                $class = 'bca-textarea__textarea';
                $divClass = 'bca-textarea';
                break;
            case 'checkbox':
                if (!isset($options['label'])) {
                    $options['label'] = false;
                }
                $class = 'bca-checkbox__input';
                $divClass = 'bca-checkbox';
                $labelClass = 'bca-checkbox__label';
                break;
            case 'select':
                if (!empty($options['multiple']) && $options['multiple'] === 'checkbox') {
                    $options['label'] = true;
                    $class = 'bca-checkbox__input';
                    $divClass = 'bca-checkbox-group';
                    $labelClass = 'bca-checkbox__label';
                    $childDivClass = 'bca-checkbox';
                } else {
                    if (!isset($options['label'])) {
                        $options['label'] = false;
                    }
                    $class = 'bca-select__select';
                    $divClass = 'bca-select';
                }
                break;
            case 'radio':
                if (!isset($options['legend'])) {
                    $options['legend'] = false;
                }
                if (!isset($options['separator'])) {
                    $options['separator'] = '　';
                }
                $options['label'] = true;
                $class = 'bca-radio__input';
                $divClass = 'bca-radio-group';
                $labelClass = 'bca-radio__label';
                $childDivClass = 'bca-radio';
                break;
        }

        if (!isset($options['div'])) {
            $options['div'] = ['tag' => 'span', 'class' => $divClass];
        }
        if (!isset($options['class'])) {
            $options['class'] = $class;
        }
        if (!isset($options['label']['class'])) {
            if (!empty($options['label'])) {
                if ($options['label'] !== true) {
                    $options['label'] = ['text' => $options['label'], 'class' => $labelClass];
                } else {
                    $options['label'] = ['class' => $labelClass];
                }
            }

        }
        if (!$type) {
            unset($options['class']);
        }
        // <<<

        $this->setEntity($fieldName);
        $options = $this->_parseOptions($options);

        $divOptions = $this->_divOptions($options);
        // CUSTOMIZE MODIFY 2018/10/13 ryuring
        // checkboxのdivを外せるオプションを追加
        // >>>
        //unset($options['div']);
        // ---
        if ($childDivClass && $options['div'] !== false) {
            $options['div']['class'] = $childDivClass;
        } elseif ($options['div'] !== false) {
            unset($options['div']);
        }
        // <<<

        if ($options['type'] === 'radio' && isset($options['options'])) {
            $radioOptions = (array)$options['options'];
            unset($options['options']);
        } else {
            $radioOptions = [];
        }

        // CUSTOMIZE MODIFY 2014/10/27 ryuring
        // >>>
        //$label = $this->_getLabel($fieldName, $options);
        //if ($options['type'] !== 'radio') {
        // ---
        if ($options['type'] === 'checkbox' || (isset($options['multiple']) && $options['multiple'] === 'checkbox')) {
            $label = '';
        } else {
            $label = $this->_getLabel($fieldName, $options);
        }
        if ($options['type'] !== 'radio' && $options['type'] !== 'checkbox' && (!isset($options['multiple']) || $options['multiple'] !== 'checkbox')) {
            // <<<
            unset($options['label']);
        }

        $error = $this->_extractOption('error', $options, null);
        unset($options['error']);

        $errorMessage = $this->_extractOption('errorMessage', $options, true);
        unset($options['errorMessage']);

        $selected = $this->_extractOption('selected', $options, null);
        unset($options['selected']);

        if ($options['type'] === 'datetime' || $options['type'] === 'date' || $options['type'] === 'time') {
            $dateFormat = $this->_extractOption('dateFormat', $options, 'MDY');
            $timeFormat = $this->_extractOption('timeFormat', $options, 12);
            unset($options['dateFormat'], $options['timeFormat']);
        } else {
            $dateFormat = 'MDY';
            $timeFormat = 12;
        }

        $type = $options['type'];
        $out = ['before' => $options['before'], 'label' => $label, 'between' => $options['between'], 'after' => $options['after']];
        $format = $this->_getFormat($options);

        unset($options['type'], $options['before'], $options['between'], $options['after'], $options['format']);

        $out['error'] = null;
        if ($type !== 'hidden' && $error !== false) {
            $errMsg = $this->error($fieldName, $error);
            if ($errMsg) {
                $divOptions = $this->addClass($divOptions, Hash::get($divOptions, 'errorClass', 'error'));
                if ($errorMessage) {
                    $out['error'] = $errMsg;
                }
            }
        }

        if ($type === 'radio' && isset($out['between'])) {
            $options['between'] = $out['between'];
            $out['between'] = null;
        }
        $out['input'] = $this->_getInput(compact('type', 'fieldName', 'options', 'radioOptions', 'selected', 'dateFormat', 'timeFormat'));

        $output = '';
        foreach($format as $element) {
            $output .= $out[$element];
        }

        if (!empty($divOptions['tag'])) {
            $tag = $divOptions['tag'];
            unset($divOptions['tag'], $divOptions['errorClass']);
            $output = $this->Html->tag($tag, $output, $divOptions);
        }

        // CUSTOMIZE MODIFY 2014/07/03 ryuring
        // >>>
        // return $output;
        // ---

        // EVENT Form.afterInput
        $event = $this->dispatchLayerEvent('afterInput', [
            'formId' => $this->__id,
            'data' => $this->request->getData(),
            'fieldName' => $fieldName,
            'out' => $output
        ], ['class' => 'Form', 'plugin' => '']);

        if ($event !== false) {
            $output = ($event->getResult() === null || $event->getResult() === true)? $event->getData('out') : $event->getResult();
        }

        return $output;
        // <<<
    }

    /**
     * Creates a hidden input field.
     *
     * @param string $fieldName Name of a field, in the form of "Modelname.fieldname"
     * @param array $options Array of HTML attributes.
     * @return string A generated hidden input
     * @link https://book.cakephp.org/2.0/en/core-libraries/helpers/form.html#FormHelper::hidden
     * @checked
     * @noTodo
     */
    public function hidden($fieldName, $options = []): string
    {
        $options += ['required' => false, 'secure' => true];

        $secure = $options['secure'];
        unset($options['secure']);

        // CUSTOMIZE ADD 2010/07/24 ryuring
        // セキュリティコンポーネントのトークン生成の仕様として、
        // ・hiddenタグ以外はフィールド情報のみ
        // ・hiddenタグはフィールド情報と値
        // をキーとして生成するようになっている。
        // その場合、生成の元のなる値は、multipleを想定されておらず、先頭の値のみとなるが
        // multiple な hiddenタグの場合、送信される値は配列で送信されるので値違いで認証がとおらない。
        // という事で、multiple の場合は、あくまでhiddenタグ以外のようにフィールド情報のみを
        // トークンのキーとする事で認証を通すようにする。
        // CUSTOMIZE ADD 2022/12/21 by ryuring
        // CakePHP4になり、動作しなくなったため、unlockField を追加
        // >>>
        if (!empty($options['multiple'])) {
            $secure = false;
            $this->unlockField($fieldName);
        }
        // <<<

        $options = $this->_initInputField($fieldName, array_merge(
            $options,
            ['secure' => static::SECURE_SKIP]
        ));

        if ($secure === true && $this->formProtector) {
            $this->formProtector->addField(
                $options['name'],
                true,
                $options['val'] === false? '0' : (string)$options['val']
            );
        }

        $options['type'] = 'hidden';

        // CUSTOMIZE ADD 2010/07/24 ryuring
        // 配列用のhiddenタグを出力できるオプションを追加
        // CUSTOMIZE 2010/08/01 ryuring
        // class属性を指定できるようにした
        // CUSTOMIZE 2011/03/11 ryuring
        // multiple で送信する値が配列の添字となっていたので配列の値に変更した
        // >>>
        $multiple = false;
        $value = '';
        if (!empty($options['multiple'])) {
            $multiple = true;
            $options['id'] = null;
            if (!isset($options['val'])) {
                $value = $this->getSourceValue($fieldName);
            } else {
                $value = $options['val'];
            }
            if (is_array($value) && !$value) {
                unset($options['val']);
            }
            unset($options['multiple']);
        }
        // <<<

        // CUSTOMIZE MODIFY 2010/07/24 ryuring
        // >>>
        // return $this->widget('hidden', $options);
        // ---
        if ($multiple && is_array($value)) {
            $out = [];
            $options['name'] = $options['name'] . '[]';
            foreach($value as $v) {
                $options['val'] = $v;
                $out[] = $this->widget('hidden', $options);;
            }
            return implode("\n", $out);
        } else {
            return $this->widget('hidden', $options);
        }
        // <<<
    }

    /**
     * Creates a submit button element. This method will generate `<input />` elements that
     * can be used to submit, and reset forms by using $options. image submits can be created by supplying an
     * image path for $caption.
     *
     * ### Options
     *
     * - `div` - Include a wrapping div?  Defaults to true. Accepts sub options similar to
     *   FormHelper::input().
     * - `before` - Content to include before the input.
     * - `after` - Content to include after the input.
     * - `type` - Set to 'reset' for reset inputs. Defaults to 'submit'
     * - `confirm` - JavaScript confirmation message.
     * - Other attributes will be assigned to the input element.
     *
     * ### Options
     *
     * - `div` - Include a wrapping div?  Defaults to true. Accepts sub options similar to
     *   FormHelper::input().
     * - Other attributes will be assigned to the input element.
     *
     * @param string $caption The label appearing on the button OR if string contains :// or the
     *  extension .jpg, .jpe, .jpeg, .gif, .png use an image if the extension
     *  exists, AND the first character is /, image is relative to webroot,
     *  OR if the first character is not /, image is relative to webroot/img.
     * @param array $options Array of options. See above.
     * @return string A HTML submit button
     * @link https://book.cakephp.org/2.0/en/core-libraries/helpers/form.html#FormHelper::submit
     * @checked
     * @noTodo
     * @unitTest
     */
    public function submit(string $caption = null, array $options = []): string
    {
        // CUSTOMIZE ADD 2016/06/08 ryuring
        // >>>
        // EVENT Form.beforeSubmit
        $event = $this->dispatchLayerEvent('beforeSubmit', [
            'id' => $this->getId(),
            'caption' => $caption,
            'options' => $options
        ], ['class' => 'Form', 'plugin' => '']);
        if ($event !== false) {
            $options = ($event->getResult() === null || $event->getResult() === true)? $event->getData('options') : $event->getResult();
        }

        $output = parent::submit($caption, $options);

        // EVENT Form.afterSubmit
        $event = $this->dispatchLayerEvent('afterSubmit', [
            'id' => $this->getId(),
            'caption' => $caption,
            'out' => $output
        ], ['class' => 'Form', 'plugin' => '']);
        if ($event !== false) {
            $output = ($event->getResult() === null || $event->getResult() === true)? $event->getData('out') : $event->getResult();
        }
        return $output;
        // <<<

    }

    /**
     * Returns an array of formatted OPTION/OPTGROUP elements
     *
     * @param array $elements Elements to format.
     * @param array $parents Parents for OPTGROUP.
     * @param bool $showParents Whether to show parents.
     * @param array $attributes HTML attributes.
     * @return array
     */
    protected function _selectOptions($elements = [], $parents = [], $showParents = null, $attributes = [])
    {
        $select = [];
        $attributes = array_merge(
            ['escape' => true, 'style' => null, 'value' => null, 'class' => null],
            $attributes
        );
        $selectedIsEmpty = ($attributes['value'] === '' || $attributes['value'] === null);
        $selectedIsArray = is_array($attributes['value']);

        // Cast boolean false into an integer so string comparisons can work.
        if ($attributes['value'] === false) {
            $attributes['value'] = 0;
        }

        $this->_domIdSuffixes = [];
        foreach($elements as $name => $title) {
            $htmlOptions = [];
            if (is_array($title) && (!isset($title['name']) || !isset($title['value']))) {
                if (!empty($name)) {
                    if ($attributes['style'] === 'checkbox') {
                        $select[] = $this->Html->useTag('fieldsetend');
                    } else {
                        $select[] = $this->Html->useTag('optiongroupend');
                    }
                    $parents[] = (string)$name;
                }
                $select = array_merge($select, $this->_selectOptions(
                    $title, $parents, $showParents, $attributes
                ));

                if (!empty($name)) {
                    $name = $attributes['escape']? h($name) : $name;
                    if ($attributes['style'] === 'checkbox') {
                        $select[] = $this->Html->useTag('fieldsetstart', $name);
                    } else {
                        $select[] = $this->Html->useTag('optiongroup', $name, '');
                    }
                }
                $name = null;
            } elseif (is_array($title)) {
                $htmlOptions = $title;
                $name = $title['value'];
                $title = $title['name'];
                unset($htmlOptions['name'], $htmlOptions['value']);
            }

            if ($name !== null) {
                $isNumeric = is_numeric($name);
                if ((!$selectedIsArray && !$selectedIsEmpty && (string)$attributes['value'] == (string)$name) ||
                    ($selectedIsArray && in_array((string)$name, $attributes['value'], !$isNumeric))
                ) {
                    if ($attributes['style'] === 'checkbox') {
                        $htmlOptions['checked'] = true;
                    } else {
                        $htmlOptions['selected'] = 'selected';
                    }
                }

                if ($showParents || (!in_array($title, $parents))) {
                    $title = ($attributes['escape'])? h($title) : $title;

                    $hasDisabled = !empty($attributes['disabled']);
                    if ($hasDisabled) {
                        $disabledIsArray = is_array($attributes['disabled']);
                        if ($disabledIsArray) {
                            $disabledIsNumeric = is_numeric($name);
                        }
                    }
                    if ($hasDisabled &&
                        $disabledIsArray &&
                        in_array((string)$name, $attributes['disabled'], !$disabledIsNumeric)
                    ) {
                        $htmlOptions['disabled'] = 'disabled';
                    }
                    if ($hasDisabled && !$disabledIsArray && $attributes['style'] === 'checkbox') {
                        $htmlOptions['disabled'] = $attributes['disabled'] === true? 'disabled' : $attributes['disabled'];
                    }

                    if ($attributes['style'] === 'checkbox') {
                        $htmlOptions['value'] = $name;

                        $tagName = $attributes['id'] . $this->domIdSuffix($name, 'html5');
                        $htmlOptions['id'] = $tagName;
                        $label = ['for' => $tagName];

                        if (isset($htmlOptions['checked']) && $htmlOptions['checked'] === true) {
                            $label['class'] = 'selected';
                        }

                        $name = $attributes['name'];

                        if (empty($attributes['class'])) {
                            $attributes['class'] = 'checkbox';
                        } elseif ($attributes['class'] === 'form-error') {
                            $attributes['class'] = 'checkbox ' . $attributes['class'];
                        }

                        // CUSTOMIZE MODIFY 2014/02/24 ryuring
                        // checkboxのdivを外せるオプションを追加
                        // CUSTOMIZE MODIFY 2014/10/27 ryuring
                        // チェックボックスをラベルタグで囲う仕様に変更した
                        // CUSTOMIZE MODIFY 2017/2/19 ryuring
                        // チェックボックスをラベルタグで囲わない仕様に変更した
                        // CUSTOMIZE MODIFY 2018/09/14 ryuring
                        // チェックボックスとラベルを span タグで挟めるようにした
                        // CUSTOMIZE MODIFY 2018/10/14 ryuring
                        // ラベルのクラスを指定できるようにした
                        // span のクラスを指定できるようにした
                        // >>>
                        // $label = $this->label(null, $title, $label);
                        // $item = $this->Html->useTag('checkboxmultiple', $name, $htmlOptions);
                        // $select[] = $this->Html->div($attributes['class'], $item . $label);
                        // ---
                        if (!empty($attributes['class'])) {
                            $htmlOptions['class'] = $attributes['class'];
                        }
                        if (!empty($attributes['label']) && is_array($attributes['label'])) {
                            if (!empty($attributes['label']['class'])) {
                                if (!empty($label['class']) && $label['class'] === 'selected') {
                                    $label['class'] .= ' ' . $attributes['label']['class'];
                                } else {
                                    $label['class'] = $attributes['label']['class'];
                                }
                            }
                            $label = array_merge($label, $attributes['label']);
                        }
                        $item = $this->Html->useTag('checkboxmultiple', $name, $htmlOptions) . $this->label(null, $title, $label);
                        if (isset($attributes['div'])) {
                            if ($attributes['div'] === false) {
                                $select[] = $item;
                            } elseif (is_array($attributes['div'])) {
                                $divOptions = $attributes['div'];
                                $tag = 'div';
                                if (!empty($divOptions['tag'])) {
                                    $tag = $divOptions['tag'];
                                }
                                unset($divOptions['tag'], $divOptions['errorClass']);
                                $select[] = $this->Html->tag($tag, $item, $divOptions);
                            } else {
                                $select[] = $this->Html->div($attributes['class'], $item);
                            }
                        } else {
                            $select[] = $this->Html->div($attributes['class'], $item);
                        }
                        // <<<

                    } else {
                        if ($attributes['escape']) {
                            $name = h($name);
                        }
                        $select[] = $this->Html->useTag('selectoption', $name, $htmlOptions, $title);
                    }
                }
            }
        }

        return array_reverse($select, true);
    }

    /**
     * Generates option lists for common <select /> menus
     *
     * @param string $name List type name.
     * @param array $options Options list.
     * @return array
     */
    protected function _generateOptions($name, $options = [])
    {
        if (!empty($this->options[$name])) {
            return $this->options[$name];
        }
        $data = [];

        switch($name) {
            case 'minute':
                if (isset($options['interval'])) {
                    $interval = $options['interval'];
                } else {
                    $interval = 1;
                }
                $i = 0;
                while($i < 60) {
                    $data[sprintf('%02d', $i)] = sprintf('%02d', $i);
                    $i += $interval;
                }
                break;
            case 'hour':
                for($i = 1; $i <= 12; $i++) {
                    $data[sprintf('%02d', $i)] = $i;
                }
                break;
            case 'hour24':
                for($i = 0; $i <= 23; $i++) {
                    $data[sprintf('%02d', $i)] = $i;
                }
                break;
            case 'meridian':
                $data = ['am' => 'am', 'pm' => 'pm'];
                break;
            case 'day':
                for($i = 1; $i <= 31; $i++) {
                    $data[sprintf('%02d', $i)] = $i;
                }
                break;
            case 'month':
                if ($options['monthNames'] === true) {
                    $data['01'] = __d('cake', 'January');
                    $data['02'] = __d('cake', 'February');
                    $data['03'] = __d('cake', 'March');
                    $data['04'] = __d('cake', 'April');
                    $data['05'] = __d('cake', 'May');
                    $data['06'] = __d('cake', 'June');
                    $data['07'] = __d('cake', 'July');
                    $data['08'] = __d('cake', 'August');
                    $data['09'] = __d('cake', 'September');
                    $data['10'] = __d('cake', 'October');
                    $data['11'] = __d('cake', 'November');
                    $data['12'] = __d('cake', 'December');
                } elseif (is_array($options['monthNames'])) {
                    $data = $options['monthNames'];
                } else {
                    for($m = 1; $m <= 12; $m++) {
                        $data[sprintf("%02s", $m)] = strftime("%m", mktime(1, 1, 1, $m, 1, 1999));
                    }
                }
                break;
            case 'year':
                $current = (int)date('Y');

                $min = !isset($options['min'])? $current - 20 : (int)$options['min'];
                $max = !isset($options['max'])? $current + 20 : (int)$options['max'];

                if ($min > $max) {
                    [$min, $max] = [$max, $min];
                }
                if (!empty($options['value']) &&
                    (int)$options['value'] < $min &&
                    (int)$options['value'] > 0
                ) {
                    $min = (int)$options['value'];
                } elseif (!empty($options['value']) && (int)$options['value'] > $max) {
                    $max = (int)$options['value'];
                }

                for($i = $min; $i <= $max; $i++) {
                    $data[$i] = $i;
                }
                if ($options['order'] !== 'asc') {
                    $data = array_reverse($data, true);
                }
                break;
            // >>> CUSTOMIZE ADD 2011/01/11 ryuring	和暦対応
            case 'wyear':
                $current = intval(date('Y'));

                if (!isset($options['min'])) {
                    $min = $current - 20;
                } else {
                    $min = $options['min'];
                }

                if (!isset($options['max'])) {
                    $max = $current + 20;
                } else {
                    $max = $options['max'];
                }
                if ($min > $max) {
                    [$min, $max] = [$max, $min];
                }
                for($i = $min; $i <= $max; $i++) {
                    $wyears = $this->BcTime->convertToWarekiYear($i);
                    if ($wyears) {
                        foreach($wyears as $value) {
                            [$w, $year] = explode('-', $value);
                            $data[$value] = $this->BcTime->nengo($w) . ' ' . $year;
                        }
                    }
                }
                $data = array_reverse($data, true);
                break;
            // <<<
        }
        $this->_options[$name] = $data;
        return $this->_options[$name];
    }

    /**
     * Creates a set of radio widgets. Will create a legend and fieldset
     * by default. Use $options to control this
     *
     * You can also customize each radio input element using an array of arrays:
     *
     * ```
     * $options = array(
     *  array('name' => 'United states', 'value' => 'US', 'title' => 'My title'),
     *  array('name' => 'Germany', 'value' => 'DE', 'class' => 'de-de', 'title' => 'Another title'),
     * );
     * ```
     *
     * ### Attributes:
     *
     * - `separator` - define the string in between the radio buttons
     * - `between` - the string between legend and input set or array of strings to insert
     *    strings between each input block
     * - `legend` - control whether or not the widget set has a fieldset & legend
     * - `fieldset` - sets the class of the fieldset. Fieldset is only generated if legend attribute is provided
     * - `value` - indicate a value that is should be checked
     * - `label` - boolean to indicate whether or not labels for widgets show be displayed
     * - `hiddenField` - boolean to indicate if you want the results of radio() to include
     *    a hidden input with a value of ''. This is useful for creating radio sets that non-continuous
     * - `disabled` - Set to `true` or `disabled` to disable all the radio buttons.
     * - `empty` - Set to `true` to create an input with the value '' as the first option. When `true`
     *   the radio label will be 'empty'. Set this option to a string to control the label value.
     *
     * @param string $fieldName Name of a field, like this "Modelname.fieldname"
     * @param array $options Radio button options array.
     * @param array $attributes Array of HTML attributes, and special attributes above.
     * @return string Completed radio widget set.
     * @link https://book.cakephp.org/2.0/en/core-libraries/helpers/form.html#options-for-select-checkbox-and-radio-inputs
     */
    public function radio($fieldName, $options = [], $attributes = []): string
    {
        // TODO ucmitz 暫定措置
        // >>>
        return parent::radio($fieldName, $options, $attributes);
        // <<<

        $attributes['options'] = $options;
        $attributes = $this->_initInputField($fieldName, $attributes);
        unset($attributes['options']);

        $showEmpty = $this->_extractOption('empty', $attributes);
        if ($showEmpty) {
            $showEmpty = ($showEmpty === true)? __d('cake', 'empty') : $showEmpty;
            $options = ['' => $showEmpty] + $options;
        }
        unset($attributes['empty']);

        $legend = false;
        if (isset($attributes['legend'])) {
            $legend = $attributes['legend'];
            unset($attributes['legend']);
        } elseif (count($options) > 1) {
            $legend = Inflector::humanize($this->field());
        }

        $fieldsetAttrs = '';
        if (isset($attributes['fieldset'])) {
            $fieldsetAttrs = ['class' => $attributes['fieldset']];
            unset($attributes['fieldset']);
        }

        $label = true;
        if (isset($attributes['label'])) {
            $label = $attributes['label'];
            unset($attributes['label']);
        }

        $separator = null;
        if (isset($attributes['separator'])) {
            $separator = $attributes['separator'];
            unset($attributes['separator']);
        }

        $between = null;
        if (isset($attributes['between'])) {
            $between = $attributes['between'];
            unset($attributes['between']);
        }

        $value = null;
        if (isset($attributes['value'])) {
            $value = $attributes['value'];
        } else {
            $value = $this->getSourceValue($fieldName);
        }

        $disabled = [];
        if (isset($attributes['disabled'])) {
            $disabled = $attributes['disabled'];
        }

        $out = [];

        $hiddenField = isset($attributes['hiddenField'])? $attributes['hiddenField'] : true;
        unset($attributes['hiddenField']);

        if (isset($value) && is_bool($value)) {
            $value = $value? 1 : 0;
        }

        $div = null;
        if (!empty($attributes['div'])) {
            $div = $attributes['div'];
            unset($attributes['div']);
        }
        if (isset($label['label'])) {
            unset($label['label']);
        }

        $this->_domIdSuffixes = [];
        foreach($options as $optValue => $optTitle) {
            $optionsHere = ['value' => $optValue, 'disabled' => false];
            if (is_array($optTitle)) {
                if (isset($optTitle['value'])) {
                    $optionsHere['value'] = $optTitle['value'];
                }

                $optionsHere += $optTitle;
                $optTitle = $optionsHere['name'];
                unset($optionsHere['name']);
            }

            if (isset($value) && strval($optValue) === strval($value)) {
                $optionsHere['checked'] = 'checked';
            }
            $isNumeric = is_numeric($optValue);
            if ($disabled && (!is_array($disabled) || in_array((string)$optValue, $disabled, !$isNumeric))) {
                $optionsHere['disabled'] = true;
            }
            $tagName = $attributes['id'] . $this->domIdSuffix($optValue, 'html5');

            if ($label) {
                $labelOpts = is_array($label)? $label : [];
                $labelOpts += ['for' => $tagName];
                $optTitle = $this->label($tagName, $optTitle, $labelOpts);
            }

            if (is_array($between)) {
                $optTitle .= array_shift($between);
            }
            $allOptions = $optionsHere + $attributes;
            // CUSTOMIZE MODIFY 2018/09/14 ryuring
            // span タグで挟む
            // >>>
            /*$out[] = $this->Html->useTag('radio', $attributes['name'], $tagName,
                array_diff_key($allOptions, array('name' => null, 'type' => null, 'id' => null)),
                $optTitle
            );*/
            // ---
            $radio = $this->Html->useTag('radio', $attributes['name'], $tagName,
                array_diff_key($allOptions, ['name' => null, 'type' => null, 'id' => null]),
                $optTitle
            );
            if (isset($div)) {
                if ($div === false) {
                    $out[] = $radio;
                } elseif (is_array($div)) {
                    $divOptions = $div;
                    $tag = 'div';
                    if (!empty($divOptions['tag'])) {
                        $tag = $divOptions['tag'];
                    }
                    unset($divOptions['tag'], $divOptions['errorClass']);
                    $out[] = $this->Html->tag($tag, $radio, $divOptions);
                } else {
                    $out[] = $this->Html->div($attributes['class'], $radio);
                }
            } else {
                $out[] = $radio;
            }
            // <<<
        }
        $hidden = null;

        if ($hiddenField) {
            if (!isset($value) || $value === '') {
                $hidden = $this->hidden($fieldName, [
                    'form' => isset($attributes['form'])? $attributes['form'] : null,
                    'id' => $attributes['id'] . '_',
                    'value' => $hiddenField === true? '' : $hiddenField,
                    'name' => $attributes['name']
                ]);
            }
        }
        $out = $hidden . implode($separator, $out);

        if (is_array($between)) {
            $between = '';
        }

        if ($legend) {
            $out = $this->Html->useTag('legend', $legend) . $between . $out;
            $out = $this->Html->useTag('fieldset', $fieldsetAttrs, $out);
        }
        return $out;
    }

// CUSTOMIZE ADD 2014/07/02 ryuring

    /**
     * フォームのIDを作成する
     * BcForm::create より呼出される事が前提
     *
     * @param EntityInterface $context
     * @param array $options
     * @return string
     * @checked
     * @noTodo
     * @unitTest
     */
    protected function createId($context, $options = [])
    {
        $request = $this->getView()->getRequest();
        if (!isset($options['id'])) {
            if (!empty($context)) {
                if (is_array($context)) {
                    // 複数$contextに設定されてる場合先頭のエンティティを優先
                    $context = array_shift($context);
                }
                if ($context instanceof EntityInterface) {
                    [, $context] = pluginSplit($context->getSource());
                } else {
                    $context = null;
                }
            }
            if (!$context) {
                $context = empty($request->getParam('controller'))? false : $request->getParam('controller');
            }
            if ($domId = isset($options['url']['action'])? $options['url']['action'] : $request->getParam('action')) {
                $formId = Inflector::classify($context) . $request->getParam('prefix') . Inflector::camelize($domId) . 'Form';
            } else {
                $formId = null;
            }
        } else {
            $formId = $options['id'];
        }
        return $formId;
    }

    /**
     * フォームのIDを取得する
     *
     * BcFormHelper::create() の後に呼び出される事を前提とする
     *
     * @return string フォームID
     * @checked
     * @noTodo
     * @unitTest
     */
    public function getId()
    {
        return $this->formId;
    }

    /**
     * フォームのIDを設定する
     *
     * BcFormHelper::create() の後に呼び出される事を前提とする
     * @param $id フォームID
     * @return string 新規フォームID
     * @checked
     * @noTodo
     * @unitTest
     */
    public function setId($id)
    {
        return $this->formId = $id;
    }

    /**
     * CKEditorを出力する
     *
     * @param string $fieldName
     * @param array $options
     * @param array $editorOptions
     * @param array $styles
     * @return    string
     * @access    public
     */
    public function ckeditor($fieldName, $options = [])
    {

        $options = array_merge(['type' => 'textarea'], $options);
        return $this->BcCkeditor->editor($fieldName, $options);
    }

    /**
     * エディタを表示する
     *
     * @param string $fieldName
     * @param array $options
     * @return string
     */
    public function editor($fieldName, $options = [])
    {
        $options = array_merge([
            'editor' => 'BaserCore.BcCkeditor',
            'style' => 'width:99%;height:540px'
        ], $options);
        if (!$options['editor']) {
            /** @var BcCkeditorHelper $bcCkeditor */
            $bcCkeditor = $this->getView()->BcCkeditor;
            return $bcCkeditor->editor($fieldName, $options);
        }
        $this->_View->loadHelper($options['editor']);
        [, $editor] = pluginSplit($options['editor']);
        if (!empty($this->getView()->{$editor})) {
            return $this->getView()->{$editor}->editor($fieldName, $options);
        } elseif ($editor === 'none') {
            $_options = [];
            foreach($options as $key => $value) {
                if (!preg_match('/^editor/', $key)) {
                    $_options[$key] = $value;
                }
            }
            return $this->input($fieldName, array_merge(['type' => 'textarea'], $_options));
        } else {
            /** @var BcCkeditorHelper $bcCkeditor */
            $bcCkeditor = $this->getView()->BcCkeditor;
            return $bcCkeditor->editor($fieldName, $options);
        }
    }

    /**
     * 都道府県用のSELECTタグを表示する
     *
     * @param string $fieldName Name attribute of the SELECT
     * @param mixed $selected Selected option
     * @param array $attributes Array of HTML options for the opening SELECT element
     * @param array $convertKey true value = "value" / false value = "key"
     * @return string 都道府県用のSELECTタグ
     */
    public function prefTag($fieldName, $selected = null, $attributes = [], $convertKey = false)
    {
        $prefs = $this->BcText->prefList();
        if ($convertKey) {
            $options = [];
            foreach($prefs as $key => $value) {
                if ($key) {
                    $options[$value] = $value;
                } else {
                    $options[$key] = $value;
                }
            }
        } else {
            $options = $prefs;
        }
        $attributes['value'] = $selected;
        $attributes['empty'] = false;
        return $this->select($fieldName, $options, $attributes);
    }

    /**
     * モデルよりリストを生成する
     *
     * @param string $modelName
     * @param mixed $conditions
     * @param mixed $fields
     * @param mixed $order
     * @return mixed リストまたは、false
     */
    public function generateList($modelName, $conditions = [], $fields = [], $order = [])
    {

        $model = ClassRegistry::init($modelName);
        if (!$model) {
            return 'aaa';
        }
        if ($fields) {
            [$idField, $displayField] = $fields;
        } else {
            return false;
        }

        $list = $model->find('all', ['conditions' => $conditions, 'fields' => $fields, 'order' => $order]);

        if ($list) {
            return Hash::combine($list, "{n}." . $modelName . "." . $idField, "{n}." . $modelName . "." . $displayField);
        } else {
            return null;
        }
    }

    /**
     * JsonList
     *
     * @param string $field フィールド文字列
     * @param string $attributes
     * @return array 属性
     */
    public function jsonList($field, $attributes)
    {

        am(["imgSrc" => "", "ajaxAddAction" => "", "ajaxDelAction" => ""], $attributes);
        // JsonDb用Hiddenタグ
        $out = $this->hidden('Json.' . $field . '.db');
        // 追加テキストボックス
        $out .= $this->text('Json.' . $field . '.name');
        // 追加ボタン
        $out .= $this->button(__d('baser_core', '追加'), ['id' => 'btnAdd' . $field]);
        // リスト表示用ビュー
        $out .= '<div id="Json' . $field . 'View"></div>';

        // javascript
        $out .= '<script type="text/javascript"><!--' . "\n" .
            'jQuery(function(){' . "\n" .
            'var json_List = new JsonList({"dbId":"Json' . $field . 'Db","viewId":"JsonTagView","addButtonId":"btnAdd' . $field . '",' . "\n" .
            '"deleteButtonType":"img","deleteButtonSrc":"' . $attributes['imgSrc'] . '","deleteButtonRollOver":true,' . "\n" .
            '"ajaxAddAction":"' . $attributes['ajaxAddAction'] . '",' . "\n" .
            '"ajaxDelAction":"' . $attributes['ajaxDelAction'] . '"});' . "\n" .
            'json_List.loadData();' . "\n" .
            '});' . "\n" .
            '//--></script>';

        return $out;
    }

    /**
     * 文字列保存用複数選択コントロール
     *
     * @param string $fieldName id,nameなどの名前
     * @param array $options optionタグの値
     * @param mixed $selected selectedを付与する要素
     * @param array $attributes htmlの属性
     * @param mixed $showEmpty 空要素の表示/非表示、初期値
     * @return string
     */
    public function selectText($fieldName, $options = [], $selected = null, $attributes = [], $showEmpty = '')
    {

        $_attributes = ['separator' => '<br />', 'quotes' => true];
        $attributes = Hash::merge($_attributes, $attributes);

        // $selected、$showEmptyをFormHelperのselect()に対応
        $attributes += [
            'value' => $selected,
            'empty' => $showEmpty
        ];

        $quotes = $attributes['quotes'];
        unset($attributes['quotes']);

        $_options = $this->_initInputField($fieldName, $options);
        if (empty($attributes['multiple']))
            $attributes['multiple'] = 'checkbox';
        $id = $_options['id'];
        $_id = $_options['id'] . '_';
        $name = $_options['name'];
        $out = '<div id="' . $_id . '">' . $this->select($fieldName . '_', $options, $attributes) . '</div>';
        $out .= $this->hidden($fieldName);
        $script = <<< DOC_END
$(function() {
    aryValue = $("#{$id}").val().replace(/\'/g,"").split(",");
    for(key in aryValue){
        var value = aryValue[key];
        $("#"+camelize("{$id}_"+value)).prop('checked',true);
    }
    $("#{$_id} input[type=checkbox]").change(function(){
        var aryValue = [];
        $("#{$_id} input[type=checkbox]").each(function(key,value){
            if($(this).prop('checked')){
                aryValue.push("'"+$(this).val()+"'");
            }
        });
        $("#{$id}").val(aryValue.join(','));
    });
});
DOC_END;
        $out .= $this->Js->buffer($script);
        return $out;
    }

    /**
     * ファイルインプットボックス出力
     *
     * 画像の場合は画像タグ、その他の場合はファイルへのリンク
     * そして削除用のチェックボックスを表示する
     *
     * 《オプション》
     * imgsize    画像のサイズを指定する
     * rel        A タグの rel 属性を指定
     * title    A タグの title 属性を指定
     * link        大きいサイズへの画像へのリンク有無
     * delCheck    削除用チェックボックスの利用可否
     * force    ファイルの存在有無に関わらず強制的に画像タグを表示するかどうか
     *
     * @param string $fieldName
     * @param array $options
     * @return string
     * @checked
     * @noTodo
     * @unitTest
     */
    public function file($fieldName, $options = []): string
    {
        $options = $this->_initInputField($fieldName, $options);

        $table = $this->getTable($fieldName);
        if (!$table || !$table->hasBehavior('BcUpload')) {
            return parent::file($fieldName, $options);
        }

        $options = array_merge([
            'imgsize' => 'medium', // 画像サイズ
            'rel' => '', // rel属性
            'title' => '', // タイトル属性
            'link' => true, // 大きいサイズの画像へのリンク有無
            'delCheck' => true,
            'force' => false,
            'width' => '',
            'height' => '',
            'class' => '',
            'div' => false,
            'deleteSpan' => [],
            'deleteCheckbox' => [],
            'deleteLabel' => [],
            'figure' => [],
            'img' => ['class' => ''],
            'figcaption' => [],
            'table' => null
        ], $options);

        $linkOptions = [
            'imgsize' => $options['imgsize'],
            'rel' => $options['rel'],
            'title' => $options['title'],
            'link' => $options['link'],
            'delCheck' => $options['delCheck'],
            'force' => $options['force'],
            'width' => $options['width'],
            'height' => $options['height'],
            'figure' => $options['figure'],
            'img' => $options['img'],
            'figcaption' => $options['figcaption'],
            'table' => $options['table']
        ];

        $deleteSpanOptions = $deleteCheckboxOptions = $deleteLabelOptions = [];
        if (!empty($options['deleteSpan'])) {
            $deleteSpanOptions = $options['deleteSpan'];
        }
        if (!empty($options['deleteCheckbox'])) {
            $deleteCheckboxOptions = $options['deleteCheckbox'];
        }
        if (!empty($options['deleteLabel'])) {
            $deleteLabelOptions = $options['deleteLabel'];
        }
        if (!empty($options['div'])) {
            $divOptions = $options['div'];
        }
        if (empty($options['class'])) {
            unset($options['class']);
        }
        unset($options['imgsize'], $options['rel'], $options['title'], $options['link']);
        unset($options['delCheck'], $options['force'], $options['width'], $options['height']);
        unset($options['deleteSpan'], $options['deleteCheckbox'], $options['deleteLabel']);
        unset($options['figure'], $options['img'], $options['figcaption'], $options['div'], $options['table']);

        $fileLinkTag = $this->BcUpload->fileLink($fieldName, $this->_getContext()->entity(), $linkOptions);
        $fileTag = parent::file($fieldName, $options);

        if (empty($options['value'])) {
            $value = $this->getSourceValue($fieldName);
        } else {
            $value = $options['value'];
        }

        // PHP5.3対応のため、is_string($value) 判別を実行
        $delCheckTag = '';
        if ($fileLinkTag && $linkOptions['delCheck'] && (is_string($value) || empty($value['session_key']))) {
            $delCheckTag = $this->Html->tag('span', $this->checkbox($fieldName . '_delete', $deleteCheckboxOptions) . $this->label($fieldName . '_delete', __d('baser_core', '削除する'), $deleteLabelOptions), $deleteSpanOptions);
        }
        $hiddenValue = $this->getSourceValue($fieldName . '_');
        $fileValue = $this->getSourceValue($fieldName);

        $hiddenTag = '';
        if ($fileLinkTag) {
            if (is_array($fileValue) && empty($fileValue['tmp_name']) && $hiddenValue) {
                $hiddenTag = $this->hidden($fieldName . '_', ['value' => $hiddenValue]);
            } else {
                if (is_array($fileValue)) {
                    $fileValue = null;
                }
                $hiddenTag = $this->hidden($fieldName . '_', ['value' => $fileValue]);
            }
        }

        $out = $fileTag;

        if ($fileLinkTag) {
            $out .= '&nbsp;' . $delCheckTag . $hiddenTag . '<br />' . $fileLinkTag;
        }

        if (isset($divOptions)) {
            if ($divOptions === false) {
                return $out;
            } elseif (is_array($divOptions)) {
                $tag = 'div';
                if (!empty($divOptions['tag'])) {
                    $tag = $divOptions['tag'];
                }
                if (!empty($divOptions['class'])) {
                    $divOptions['class'] .= ' upload-file';
                } else {
                    $divOptions['class'] = 'upload-file';
                }
                unset($divOptions['tag'], $divOptions['errorClass']);
                return $this->Html->tag($tag, $out, $divOptions);
            } else {
                return $this->Html->div($options['class'], $out);
            }
        } else {
            return $out;
        }
    }

    /**
     * フィールドに紐づくテーブルを取得する
     * @param string $fieldName
     * @return \Cake\ORM\Table|false
     * @checked
     * @noTodo
     * @unitTest
     */
    public function getTable($fieldName)
    {
        $context = $this->context();
        if (!($context instanceof EntityContext)) return false;
        $entity = $context->entity(explode('.', $fieldName));
        if (!$entity) return false;

        $fieldArray = explode('.', $fieldName);

        if (count($fieldArray) === 3) {
            if ($entity && $entity->get($fieldArray[1])) {
                $entity = $entity->get($fieldArray[1]);
            }
        } elseif (!in_array(count($fieldArray), [1, 2])) {
            return false;
        }

        $alias = $entity->getSource();
        $plugin = '';
        if (strpos($alias, '.')) {
            [$plugin, $name] = pluginSplit($alias);
        }
        $name = Inflector::camelize(Inflector::tableize($name));
        if ($plugin) $name = $plugin . '.' . $name;
        return TableRegistry::getTableLocator()->get($name);
    }

    /**
     * フォームコントロールを取得
     *
     * CakePHPの標準仕様をカスタマイズ
     * - labelタグを自動で付けない
     * - legendタグを自動で付けない
     * - errorタグを自動で付けない
     *
     * @param string $fieldName
     * @param array $options
     * @return string
     * @checked
     * @noTodo
     * @unitTest
     */
    public function control(string $fieldName, array $options = []): string
    {
        $options = array_replace_recursive([
            'label' => false,
            'legend' => false,
            'error' => false,
            'counter' => false,
            'templateVars' => ['tag' => 'span', 'groupTag' => 'span']
        ], $options);

        if ($options['counter']) {
            if (!empty($options['class'])) {
                $options['class'] .= ' bca-text-counter';
            } else {
                $options['class'] = 'bca-text-counter';
            }
            unset($options['counter']);
        }
        return parent::control($fieldName, $options);
    }
// <<<

}
