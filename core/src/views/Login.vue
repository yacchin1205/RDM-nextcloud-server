<!--
  - @copyright 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
  -
  - @author 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program.  If not, see <http://www.gnu.org/licenses/>.
  -->

<template>
	<form method="post"
		  name="login"
		  @submit="submit">
		<fieldset>
			<input v-if="redirectUrl"
				   type="hidden"
				   name="redirect_url"
				   :value="redirectUrl">
			<div v-if="apacheAuthFailed"
				 class="warning">
				{{ t('core', 'Server side authentication failed!') }}<br>
				<small>{{ t('core', 'Please contact your administrator.') }}
				</small>
			</div>
			<div v-for="message in messages"
				 class="warning">
				{{ message }}<br>
			</div>
			<div v-if="internalException"
				 class="warning">
				{{ t('core', 'An internal error occurred.') }}<br>
				<small>{{ t('core', 'Please try again or contact your administrator.') }}
				</small>
			</div>
			<div id="message"
				 class="hidden">
				<img class="float-spinner" alt=""
					 :src="OC.imagePath('core', 'loading-dark.gif')">
				<span id="messageText"></span>
				<!-- the following div ensures that the spinner is always inside the #message div -->
				<div style="clear: both;"></div>
			</div>
			<p class="grouptop"
			   :class="{shake: invalidPassword}">
				<input type="text"
					   name="user"
					   id="user"
					   :placeholder="t('core', 'Username or email')"
					   :aria-label="t('core', 'Username or email')"
					   :value="username"
					   required>
				<!--<?php p($_['user_autofocus'] ? 'autofocus' : ''); ?>
				autocomplete="<?php p($_['login_form_autocomplete']); ?>" autocapitalize="none" autocorrect="off"-->
				<label for="user" class="infield">{{ t('core', 'Username or	email') }}</label>
			</p>

			<p class="groupbottom"
			   :class="{shake: invalidPassword}">
				<input type="password"
					   name="password"
					   id="password"
					   value=""
					   :placeholder="t('core', 'Password')"
					   :aria-label="t('core', 'Password')"
					   required>
				<!--<?php p($_['user_autofocus'] ? '' : 'autofocus'); ?>
				autocomplete="<?php p($_['login_form_autocomplete']); ?>" autocapitalize="none" autocorrect="off"-->
				<label for="password"
					   class="infield">{{ t('Password') }}</label>
			</p>

			<div id="submit-wrapper">
				<input type="submit"
					   id="submit"
					   class="login primary"
					   title=""
					   :value="!loading ? t('core', 'Log in') : t('core', 'Logging in …')"
					   disabled="disabled"/>
				<div class="submit-icon"
					 :class="{
					 			'icon-confirm-white': !loading,
					 			'icon-loading-small': loading && invertedColors,
					 			'icon-loading-small-dark': loading && !invertedColors,
							}"></div>
			</div>

			<p v-if="invalidPassword"
			   class="warning wrongPasswordMsg">
				{{ t('core', 'Wrong username or password.') }}
			</p>
			<p v-else-if="userDisabled"
			   class="warning userDisabledMsg">
				{{ t('lib', 'User disabled') }}
			</p>

			<p v-if="throttleDelay && throttleDelay > 5000"
			   class="warning throttledMsg">
				{{ t('core', 'We have detected multiple invalid login attempts from your IP. Therefore your next login is throttled up to 30 seconds.') }}
			</p>

			<div v-if="canResetPassword"
				 id="reset-password-wrapper"
				 style="display: none;">
				<input type="submit" id="reset-password-submit"
					   class="login primary" title=""
					   :value="t('core', 'Reset password')"
					   disabled="disabled"/>
				<div class="submit-icon icon-confirm-white"></div>
			</div>

			<div class="login-additional">
				<div v-if="canResetPassword"
					 class="lost-password-container">
					<a id="lost-password"
					   :href="resetPasswordLink">
						{{ t('core', 'Forgot password?') }}
					</a>
					<a id="lost-password-back"
					   href=""
					   style="display:none;">
						{{ t('core', 'Back to login') }}
					</a>
				</div>
			</div>

			<input type="hidden" name="timezone_offset" id="timezone_offset"/>
			<input type="hidden" name="timezone" id="timezone"/>
			<input type="hidden" name="requesttoken"
				   :value="OC.requestToken">
		</fieldset>
	</form>
</template>

<script>
	export default {
		name: 'Login',
		props: {
			username: {
				type: String,
				default: '',
			},
			redirectUrl: {
				type: String,
			},
			errors: {
				type: Array,
				default: () => [],
			},
			messages: {
				type: Array,
				default: () => [],
			},
			throttleDelay: {
				type: Number,
			},
			canResetPassword: {
				type: Boolean,
				default: false,
			},
			resetPasswordLink: {
				type: String,
			},
			invertedColors: {
				type: Boolean,
				default: false,
			}
		},
		data() {
			return {
				loading: false,
				password: '',
			}
		},
		computed: {
			apacheAuthFailed() {
				return this.errors.indexOf('apacheAuthFailed') !== -1
			},
			internalException() {
				return this.errors.indexOf('internalexception') !== -1
			},
			invalidPassword() {
				return this.errors.indexOf('invalidpassword') !== -1
			},
			userDisabled() {
				return this.errors.indexOf('userdisabled') !== -1
			},
		},
		methods: {
			submit() {
				this.loading = true
			}
		}
	}
</script>

<style scoped>

</style>