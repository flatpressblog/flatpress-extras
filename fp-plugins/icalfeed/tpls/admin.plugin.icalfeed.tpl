<h2>{$plang.head}</h2>

{include file="shared:errorlist.tpl"}

<p>{$plang.desc1}</p>
<p>{$plang.desc2}</p>

{html_form class="icalfeed-options"}

	<fieldset>
		<legend>{$plang.feed_urls_label}</legend>
		<textarea name="feed_urls" id="feed_urls" rows="5" class="wide" style="width: 100%; max-width: 900px;">{$feed_urls|escape}</textarea>
		<p class="desc">{$plang.feed_urls_help}</p>
	</fieldset>

	<fieldset>
		<legend>{$plang.mode_label}</legend>
		<select name="mode" id="mode">
			<option value="list" {if $mode == 'list'}selected{/if}>{$plang.mode_list}</option>
			<option value="busy" {if $mode == 'busy'}selected{/if}>{$plang.mode_busy}</option>
		</select>
	</fieldset>

	<fieldset>
		<legend>{$plang.privacy_label}</legend>
		<select name="privacy" id="privacy">
			<option value="details" {if $privacy == 'details'}selected{/if}>{$plang.privacy_details}</option>
			<option value="busy" {if $privacy == 'busy'}selected{/if}>{$plang.privacy_busy}</option>
		</select>
		<br>
		<label for="show_location">
			<input type="checkbox" name="show_location" id="show_location" value="1" {if isset($show_location) && $show_location}checked{/if}>
			{$plang.show_location_label}
		</label>
	</fieldset>

	<fieldset>
		<legend>{$plang.ssl_verify_label}</legend>
		<label for="ssl_verify">
			<input type="checkbox" name="ssl_verify" id="ssl_verify" value="1" {if !isset($ssl_verify) || $ssl_verify}checked{/if}>
			{$plang.ssl_verify_checkbox}
		</label>
		<p class="desc">{$plang.ssl_verify_help}</p>
	</fieldset>

	<fieldset>
		<legend>{$plang.cache_ttl_label}</legend>
		<input type="number" name="cache_ttl" id="cache_ttl" min="0" step="1" value="{$cache_ttl|escape}">
	</fieldset>

	<fieldset>
		<legend>{$plang.days_ahead_label}</legend>
		<input type="number" name="days_ahead" id="days_ahead" min="1" step="1" value="{$days_ahead|escape}">
	</fieldset>

	<fieldset>
		<legend>{$plang.display_timezone_label}</legend>
		<input type="text" name="display_timezone" id="display_timezone" value="{$display_timezone|escape}" style="width: 320px;">
		<p class="desc">{$plang.display_timezone_help}</p>
	</fieldset>

	<fieldset>
		<legend>{$plang.limit_label}</legend>
		<input type="number" name="limit" id="limit" min="1" step="1" value="{$limit|escape}">
	</fieldset>

	<div class="buttonbar">
		<input type="submit" name="save" value="{$plang.save}">
		<input type="submit" name="clear_cache" value="{$plang.clear_cache}">
	</div>

	<p><strong>{$plang.tag_usage_head}</strong><br><code>{$plang.tag_usage|escape}</code></p>

{/html_form}
