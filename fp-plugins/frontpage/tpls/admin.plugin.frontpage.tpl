
{include file="shared:errorlist.tpl"}

	

{html_form}

<h2>{$plang.frontpage}</h2>
<p>{$plang.frontdescription}</p>

<div class="option-set">

	<div id="fp-def-cats" class="option-list">
	<p><label><input name="def-cats" type="radio" value="0" {if $categories == 0}checked="checked"{/if} />{$plang.defcat}</label></p>
	{list_categories type="radio" selected="$categories" name="def-"}
	</div>

</div>
	
<h2>{$plang.hide}</h2>
<p>{$plang.hidedescription}</p>

<div class="option-set">

	
	<div id="fp-ex-cats" class="option-list">
	<p><label><input name="ex-cats" type="radio" value="0" {if $exclude_categories == 0}checked="checked"{/if} />{$plang.defexcat}</label></p>
	{list_categories type="radio" selected="$exclude_categories" name="ex-"}
	</div>
	
	<div class="buttonbar">
	<input type="submit" value="{$plang.submit}"/> 
	</div>

</div>
	
{/html_form}

