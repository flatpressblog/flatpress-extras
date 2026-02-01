{include file="header.tpl"}
	
			<main id="content-main">
				
			<div class="entry">
			{page}
					<h3 class="title">{$subject}</h3>
					<div class="body">
					
				    {if isset($rawcontent) and $rawcontent} {$content}
				    {else}	{include file=$content}{/if}
					
					</div>
			{/page}
			</div>
			
			</main>
			
    		{include file="widgets.tpl"}
			
{include file="footer.tpl"}



