<?php

defined('_JEXEC') or die('Restricted access');
?>
<tr>
	<td class="key">
		<label for="data[payment][payment_params][api]"><?php
            echo JText::_('کلید مجوزدهی Api Key');
        ?></label>
	</td>
	<td>
		<input type="text" name="data[payment][payment_params][api]" value="<?php echo $this->escape(@$this->element->payment_params->api); ?>" />
	</td>
</tr>
<tr>
	<td class="key">
		<label for="data[payment][payment_params][invalid_status]"><?php
            echo JText::_('INVALID_STATUS');
        ?></label>
	</td>
	<td><?php
        echo $this->data['order_statuses']->display('data[payment][payment_params][invalid_status]', @$this->element->payment_params->invalid_status);
    ?></td>
</tr>
<tr>
	<td class="key">
		<label for="data[payment][payment_params][pending_status]"><?php
            echo JText::_('PENDING_STATUS');
        ?></label>
	</td>
	<td><?php
        echo $this->data['order_statuses']->display('data[payment][payment_params][pending_status]', @$this->element->payment_params->pending_status);
    ?></td>
</tr>
<tr>
	<td class="key">
		<label for="data[payment][payment_params][verified_status]"><?php
            echo JText::_('VERIFIED_STATUS');
        ?></label>
	</td>
	<td><?php
        echo $this->data['order_statuses']->display('data[payment][payment_params][verified_status]', @$this->element->payment_params->verified_status);
    ?></td>
</tr>