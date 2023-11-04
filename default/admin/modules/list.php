<?php
	use fruithost\I18N;
?>
<table class="table table-borderless table-striped table-hover">
	<thead>
		<tr>
			<th scope="col" colspan="3"><?php I18N::__('Module'); ?></th>
			<th scope="col"><?php I18N::__('Description'); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php
			foreach($modules->getList() AS $module) {
				$info = $module->getInfo();
				?>
				<tr class="<?php print (isset($upgradeable->{$module->getDirectory()}) ? 'table-warning' : ''); ?>">
					<td scope="row" width="1px">
						<div class="custom-control custom-checkbox">
							<input class="custom-control-input" type="checkbox" id="module_<?php print $info->getName(); ?>" name="module[]" value="<?php print $info->getName(); ?>" />
							<label class="custom-control-label" for="module_<?php print $info->getName(); ?>"></label>
						</div>
					</td>
					<td>
						<span class="d-block badge badge-pill module-badge badge-<?php print ($module->isLocked() ? 'warning' : ($module->isEnabled() ? 'success' : 'danger'));?>" data-toggle="tooltip" title="<?php ($module->isLocked() ? I18N::__('Module has depencies.') : ($module->isEnabled() ? I18N::__('Module is enabled.') : I18N::__('Module is disabled.')));?>"></span>
					</td>
					<td>
						<strong><?php print $info->getName(); ?></strong>
						<p class="module-actions"><?php
							$links = [];
							
							if($module->isEnabled()) {
								$links['disable'] = sprintf('<a href="%s" data-confirm="%s" class="text-warning">%s</a>', $this->url('/admin/modules/?disable=' . $module->getDirectory()), I18N::get('Do you really wan\'t to disable the module?'), I18N::get('Disable'));
							} else {
								$links['enable'] = sprintf('<a href="%s" class="text-success">%s</a>', $this->url('/admin/modules/?enable=' . $module->getDirectory()), I18N::get('Enable'));
							}
							
							if($module->hasSettingsPath()) {
								$links['settings'] = sprintf('<a href="%s" class="text-primary">%s</a>', $this->url('/admin/modules/?settings=' . $module->getDirectory()), I18N::get('Settings'));
							}
							
							$links['deinstall'] = sprintf('<a href="%s" data-confirm="%s" class="text-danger">%s</a>', $this->url('/admin/modules/?deinstall=' . $module->getDirectory()), I18N::get('Do you really wan\'t to deinstall the module?'), I18N::get('Deinstall'));
							
							print implode(' | ', $links);
						?></p><?php
							if($module->isLocked()) {
								$list = [];
								foreach($module->getInfo()->getDepencies() AS $name => $version) {
									$list[] = sprintf('%s (%s)', $name, $version);
								}
								
								printf('<small>Unresolved Depencies: %s</small>', implode(',', $list));
							}
						?>
					</td>
					<td>
						<?php
							if(isset($upgradeable->{$module->getDirectory()})) {
								$upgrade = $upgradeable->{$module->getDirectory()};
								?>
									<div class="alert alert-warning d-flex p-2" role="alert">
										<i class="material-icons text-danger p-0">autorenew</i>
										<p class="p-0 m-0"><?php printf(I18N::get('The module <strong>%s</strong> has an new upgrade to <strong>Version %s</strong>!'), $info->getName(), $upgrade->version); ?></p>
										<button class="btn btn-outline-success btn-sm m-0 ml-2 pt-0 pb-0"><?php I18N::__('Upgrade now!'); ?></button>
									</div>
								<?php
							}
						?>
						<p><?php print $info->getDescription(); ?></p>
						<small><?php I18N::__('Version'); ?> <?php print $info->getVersion(); ?> | <?php I18N::__('by'); ?> <a href="<?php print $info->getAuthor()->getWebsite(); ?>" target="_blank"><?php print $info->getAuthor()->getName(); ?></a></small>
					</td>
				</tr>
				<?php
			}
		?>
	</tbody>
</table>