<?php declare(strict_types = 0);

(new CWidgetFormView($data))
    ->addField(
        new CWidgetFieldMultiSelectItemView($data['fields']['itemids'])
    )
    ->addField(
        new CWidgetFieldCheckBoxView($data['fields']['show_status'])
    )
    ->addField(
        new CWidgetFieldIntegerBoxView($data['fields']['grid_count'])
    )
    ->addField(
        (new CWidgetFieldTextAreaView($data['fields']['custom_names_json']))
            ->addRowClass(ZBX_STYLE_DISPLAY_NONE)
    )
    ->addItem([
        new CLabel('Label for element'),
        new CFormField((new CDiv())->setId('custom-names-container'))
    ])
    ->addField(
        (new CWidgetFieldTimePeriodView($data['fields']['time_period']))
            ->setDateFormat(ZBX_FULL_DATE_TIME)
            ->setFromPlaceholder(_('YYYY-MM-DD hh:mm:ss'))
            ->setToPlaceholder(_('YYYY-MM-DD hh:mm:ss'))
    )
    ->includeJsFile('widget.edit.js.php')
    ->initFormJs('widget_form.init();')
    ->show();
