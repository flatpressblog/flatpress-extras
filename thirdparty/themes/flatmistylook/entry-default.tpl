	<article id="{$id}" class="post">
				{* 	using the following way to print the date, if more 	*} 
				{*	than one entry have been written the same day,		*} 
				{*	 the date will be printed only once 				*}
						    
				<div class="posttitle">
					<h2><a href="{$id|link:post_link}" rel="bookmark" title="Permanent Link to {$subject|tag:the_title}">{$subject|tag:the_title}</a></h2>
					<p class="post-info">{$date|date_format:"%A, %B %e, %Y"} at {$date|date_format}</p>
				</div>
               				
				<div class="entry">
					{$content|tag:the_content}
				</div>
                
                {include file="shared:entryadminctrls.tpl"}
				
				<p class="postmetadata">{if ($categories)} Published by {$author} in {$categories|@filed}|{/if}  <a href="{$id|link:comments_link}#comments">{$comments|tag:comments_number}</a> </p>                
            
				<ul class="entry-footer">
				
				{if !(in_array('commslock', $categories) && !$comments)}
				<li class="link-comments">
				<a href="{$id|link:comments_link}#comments">{$comments|tag:comments_number} 
					{if isset($views)}(<strong>{$views}</strong> views){/if}
				</a>
				</li>
				{/if}
				
				</ul>			
				
	</article>
