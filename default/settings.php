<?php
	use fruithost\Auth;
	use fruithost\I18N;
	
	$template->header();
	?>
		<form method="post" action="<?php print $this->url('/settings' . (!empty($tab) ? '/' . $tab : '')); ?>">
			<header class="page-header d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
				<h1 class="h2">
					<a class="active" href="<?php print $this->url('/settings'); ?>"><?php I18N::__('Settings'); ?></a>
				</h1>
				<div class="btn-toolbar mb-2 mb-md-0">
					<button type="submit" name="action" value="save" class="btn btn-sm btn-outline-primary"><?php I18N::__('Save'); ?></button>
				</div>
			</header>
			
			<ul class="nav nav-tabs" id="myTab" role="tablist">
				<li class="nav-item"><a class="nav-link<?php print (empty($tab) ? ' active' : ''); ?>" id="globals-tab" href="<?php print $this->url('/settings'); ?>" role="tab"><?php I18N::__('Global Settings'); ?></a></li>
				<li class="nav-item"><a class="nav-link<?php print (!empty($tab) && $tab === 'security' ? ' active' : ''); ?>" id="security-tab" href="<?php print $this->url('/settings/security'); ?>" role="tab"><?php I18N::__('Security'); ?></a></li>
			</ul>
			<?php
				if(isset($error)) {
					?>
						<div class="alert alert-danger mt-4" role="alert"><?php print $error; ?></div>
					<?php
				} else if(isset($success)) {
					?>
						<div class="alert alert-success mt-4" role="alert"><?php print $success; ?></div>
					<?php
				}
			?>
			<div class="tab-content" id="myTabContent">
				<div class="tab-pane<?php print (empty($tab) ? ' show active' : ''); ?>" id="globals" role="tabpanel" aria-labelledby="globals-tab">
					<p class="text-secondary p-4"><?php I18N::__('Global settings for your usability'); ?></p>
					
					<div class="container">
						<div class="form-group row">
							<label for="language" class="col-sm-2 col-form-label"><?php I18N::__('Language'); ?>:</label>
							<div class="col-sm-10">
								<select name="language" name="language" class="form-control">
								<?php
									foreach($languages AS $code => $language) {
										printf('<option value="%1$s"%2$s>%3$s</option>', $code, (Auth::getSettings('LANGUAGE', NULL, $template->getCore()->getSettings('LANGUAGE', 'en_US')) === $code ? ' SELECTED' : ''), $language);
									}
								?>
								</select>
							</div>
						</div>
						<hr class="mb-4" />
						<div class="form-group row">
							<label for="time_format" class="col-sm-2 col-form-label"><?php I18N::__('Time Format'); ?>:</label>
							<div class="col-sm-10">
								<input type="text" class="form-control" id="time_format" name="time_format" value="<?php print Auth::getSettings('TIME_FORMAT', NULL, $template->getCore()->getSettings('TIME_FORMAT', 'd.m.Y - H:i:s')); ?>" />
							</div>
						</div>
						<div class="form-group row">
							<label for="time_zone" class="col-sm-2 col-form-label"><?php I18N::__('Timezone'); ?>:</label>
							<div class="col-sm-10">
								<select name="time_zone" id="time_zone" class="form-control">
									<?php
										foreach($timezones AS $category) {
											printf('<optgroup label="%s">', $category->group);
											
											foreach($category->zones AS $zone) {
												printf('<option value="%1$s"%2$s>%3$s</option>', $zone->value, (Auth::getSettings('TIME_ZONE', NULL, $template->getCore()->getSettings('TIME_ZONE', date_default_timezone_get())) === $zone->value ? ' SELECTED' : ''), $zone->name);
											}
											
											print('</optgroup>');
										}
									?>
								</select>
							</div>
						</div>
						<?php
							$template->getCore()->getHooks()->runAction('ACCOUNT_SETTINGS_GLOBAL');
						?>
						<div class="form-group text-right">
							<button type="submit" name="action" value="save" class="btn btn-outline-success"><?php I18N::__('Save'); ?></button>
						</div>
					</div>
				</div>
				<div class="tab-pane<?php print (!empty($tab) && $tab === 'security' ? ' show active' : ''); ?>" id="security" role="tabpanel" aria-labelledby="security-tab">
					<p class="text-secondary p-4"><?php I18N::__('Some security settings for your safety.'); ?></p>
					
					<div class="container">
						<div class="form-group row">
							<div class="col-sm-2 text-right"></div>
							<div class="col-sm-10">
								<div class="custom-control custom-checkbox">
									<input class="custom-control-input" type="checkbox" name="2fa_enabled" id="2fa_enabled" value="true"<?php print (Auth::getSettings('2FA_ENABLED', NULL, 'false') === 'true' ? ' CHECKED' : ''); ?> />
									<label class="custom-control-label" for="2fa_enabled">
										<?php I18N::__('Enable Two-factor authentication (2FA)'); ?>
									</label>
								</div>
							</div>
						</div>
						<div class="form-group row">
							<div class="col-sm-2 text-right"></div>
							<div class="col-sm-10">
								<?php
									if(Auth::getSettings('2FA_ENABLED', NULL, 'false') === 'true' && !filter_var(Auth::getMail(), FILTER_VALIDATE_EMAIL)) {
										?>
											<div class="alert alert-danger alert-dismissible fade show mt-4" role="alert">
												<?php I18N::__('Two-Factor authentication (2FA) is <strong>temporarily disabled</strong> because you did not provide a <strong>valid E-Mail address</strong>.'); ?>
												<br /><?php sprintf(I18N::get('Check your E-Mail address in the <a href="%s">account settings</a>.'), $template->url('/account')); ?>
											</div>
										<?php
									} else if(!filter_var(Auth::getMail(), FILTER_VALIDATE_EMAIL)) {
										?>
											<div class="alert alert-danger alert-dismissible fade show mt-4" role="alert">
												<?php I18N::__('Two-Factor authentication (2FA) will be <strong>temporarily disabled</strong> because you did not provide a <strong>valid E-Mail address</strong>.'); ?>
												<br /><?php sprintf(I18N::get('Check your E-Mail address in the <a href="%s">account settings</a>.'), $template->url('/account')); ?>
											</div>
										<?php
									}
								?>
							</div>
						</div>
						<div class="form-group row">
							<label for="2fa_type" class="col-sm-2 col-form-label"><?php I18N::__('Type'); ?>:</label>
							<div class="col-sm-10">
								<select name="2fa_type" class="form-control">
									<option value="app" DISABLED><?php I18N::__('Smartphone'); ?> (<?php I18N::__('disabled'); ?>)</option>
									<option value="mail" SELECTED><?php I18N::__('E-Mail'); ?></option>
									<option value="sms" DISABLED><?php I18N::__('SMS'); ?> (<?php I18N::__('disabled'); ?>)</option>
								</select>
                            </div>
						</div>
						<div class="form-group text-right">
							<button type="submit" name="action" value="save" class="btn btn-outline-success"><?php I18N::__('Save'); ?></button>
						</div>
					</div>
				</div>
			</div>
		</form>
	<?php
	$template->footer();
?>