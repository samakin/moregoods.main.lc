<?php /* Smarty version 2.6.18, created on 2018-01-13 14:09:38
         compiled from addon/mpanel/translate_message/form_add.tpl */ ?>
<?php require_once(SMARTY_CORE_DIR . 'core.load_plugins.php');
smarty_core_load_plugins(array('plugins' => array(array('modifier', 'escape', 'addon/mpanel/translate_message/form_add.tpl', 22, false),)), $this); ?>
<FORM id='main_form' action='javascript:void(null);'
	onsubmit="submit_form(this)">

<table cellspacing=0 cellpadding=2 class=add_form>
	<tr>
		<th><?php echo $this->_tpl_vars['oLanguage']->getDMessage('Translation'); ?>
</th>
	</tr>
	<tr>
		<td>

		<table cellspacing=2 cellpadding=1>
			<tr>
				<td width="100%"><?php echo $this->_tpl_vars['oLanguage']->getDMessage('Code'); ?>
: <?php echo $this->_tpl_vars['sZir']; ?>
</td>
				<td><textarea name=data[code]><?php echo $this->_tpl_vars['aData']['code']; ?>
</textarea></td>
			</tr>
			<tr>
				<td width="100%"><?php echo $this->_tpl_vars['oLanguage']->getDMessage('Translation'); ?>
:</td>
				<td><textarea name=data[content]><?php echo $this->_tpl_vars['aData']['content']; ?>
</textarea></td>
			</tr>
			<tr>
				<td width="100%"><?php echo $this->_tpl_vars['oLanguage']->getDMessage('Page'); ?>
:</td>
				<td><input type=text name=data[page] value="<?php echo ((is_array($_tmp=$this->_tpl_vars['aData']['page'])) ? $this->_run_mod_handler('escape', true, $_tmp) : smarty_modifier_escape($_tmp)); ?>
"></td>
			</tr>
		</table>

		</td>
	</tr>
</table>

<input type=hidden name=data[id] value="<?php echo ((is_array($_tmp=$this->_tpl_vars['aData']['id'])) ? $this->_run_mod_handler('escape', true, $_tmp) : smarty_modifier_escape($_tmp)); ?>
">
<?php $_smarty_tpl_vars = $this->_tpl_vars;
$this->_smarty_include(array('smarty_include_tpl_file' => 'addon/mpanel/base_add_button.tpl', 'smarty_include_vars' => array('sBaseAction' => $this->_tpl_vars['sBaseAction'])));
$this->_tpl_vars = $_smarty_tpl_vars;
unset($_smarty_tpl_vars);
 ?>

</FORM>