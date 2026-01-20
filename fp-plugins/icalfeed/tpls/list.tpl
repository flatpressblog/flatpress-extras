{* iCalFeed list renderer *}
{strip}
<div class="icalfeed">
{if $icalfeed_title}<h3 class="icalfeed-title">{$icalfeed_title|escape}</h3>{/if}
{if $icalfeed_error}
<div class="icalfeed-error">{if $icalfeed_error=='no_urls'}{$icalfeed_lang.errors.no_urls|escape}{elseif $icalfeed_error=='fetch_failed'}{$icalfeed_lang.errors.fetch_failed|escape}{elseif $icalfeed_error=='parse_failed'}{$icalfeed_lang.errors.parse_failed|escape}{else}{$icalfeed_lang.errors.generic|escape}{/if}</div>
{elseif $icalfeed_events|@count == 0}
<div class="icalfeed-empty">{if $icalfeed_mode=='busy'}{$icalfeed_lang.labels.free|escape}{else}{$icalfeed_lang.labels.no_events|escape}{/if}</div>
{else}
{if $icalfeed_mode=='busy'}
<ul class="icalfeed-busy">
{foreach from=$icalfeed_events item=ev}
<li class="icalfeed-busy-item"><span class="icalfeed-when">{$ev.start_local|date_format:($fp_config.locale.dateformatshort|default:"%Y-%m-%d")} {$ev.start_local|date_format:($fp_config.locale.timeformatshort|default:"%H:%M")} &ndash; {$ev.end_local|date_format:($fp_config.locale.dateformatshort|default:"%Y-%m-%d")} {$ev.end_local|date_format:($fp_config.locale.timeformatshort|default:"%H:%M")}</span> <span class="icalfeed-busy-label">{$icalfeed_lang.labels.busy|escape}</span></li>
{/foreach}
</ul>
{else}
<ul class="icalfeed-list">
{foreach from=$icalfeed_events item=ev}
<li class="icalfeed-item"><span class="icalfeed-when">{if $ev.all_day}{$ev.start_local|date_format:($fp_config.locale.dateformatshort|default:"%Y-%m-%d")} <span class="icalfeed-allday">{$icalfeed_lang.labels.all_day|escape}</span>{else}{$ev.start_local|date_format:($fp_config.locale.dateformatshort|default:"%Y-%m-%d")} {$ev.start_local|date_format:($fp_config.locale.timeformatshort|default:"%H:%M")}{if $ev.end_local && $ev.end_local > $ev.start_local} &ndash; {$ev.end_local|date_format:($fp_config.locale.timeformatshort|default:"%H:%M")}{/if}{/if}</span>{if $icalfeed_privacy=='details'} <span class="icalfeed-summary">{$ev.summary|escape}</span>{if $ev.location && $icalfeed_show_location} <span class="icalfeed-location">{$ev.location|escape}</span>{/if}{else} <span class="icalfeed-summary">{$icalfeed_lang.labels.busy|escape}</span>{/if}</li>
{/foreach}
</ul>
{/if}
{/if}
</div>
{/strip}
