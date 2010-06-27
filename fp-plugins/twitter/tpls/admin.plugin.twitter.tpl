<h2>{$plang.head}</h2>
<p>{$plang.description}</p>

{include file=shared:errorlist.tpl}
{html_form class=option-set}


<dl class="option-list">
	<dt><label for="userid">
		{$plang.userid}
	</label></dt>
	<dd> 
		<p><input class="textinput" type="text" name="userid" id="userid" value="{$twitterconf.userid|htmlspecialchars}" /> </p>
	</dd>

	<dt><label for="check_freq">
		{$plang.check_freq}
	</label></dt>
	<dd>
		<p>{$plang.check_freq_1} 
		<input type="text" class="smalltextinput" size="3" name="check_freq" id="check_freq" value="{$twitterconf.check_freq|default:60}" /> 
		{$plang.check_freq_2}</p>
	</dd>
	<dt><label for="category">
		{$plang.category}
	</label></dt>
	<dd> 
	<select class="textinput"  name="category">
		{html_options options=$categories_all selected=$twitterconf.category}
	</select>
	</dd>	
	<dt>
		{$plang.replies}
	</dt>
	<dd><label for="replies">
		<input type="checkbox" name="replies" id="replies" {if $twitterconf.replies}checked="checked"{/if} /> 
		{$plang.replies_descr}
	</label>
	</dd>	
</dl>

<div class="buttonbar">
<input type="submit" name="submit" value="{$plang.submit}" />
<input type="submit" name="check_now" value="{$plang.check_now}" />
</div>
		
		
{/html_form}

