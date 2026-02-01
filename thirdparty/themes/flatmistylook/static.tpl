{include file="header.tpl"}

		<main id="content-main">

        {static_block}
		{static}
		<div class="post" id="{$id}">
			<div class="posttitle">
				<h2>{$subject}</h2>
				<p class="post-info">Published by {$author} on {$date|date_format_daily}</p>
			</div>
			<div class="entry">
				{$content|tag:the_content}
			</div>
		{/static}

		{/static_block}	
				
		</main>
		
		{include file="widgets.tpl"}
	
{include file="footer.tpl"}
