{include file="header.tpl"}

		<main id="content-main">
		
		
		{entry_block}
		
			{entry}
			{include file="entry-default.tpl"}
			{/entry}
		
			<nav class="navigation">
				{nextpage}{prevpage}
			</nav>
			
		{/entry_block}

		</main>

		{include file="widgets.tpl"}
				
{include file="footer.tpl"}
