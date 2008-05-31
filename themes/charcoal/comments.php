		<?php if ( !$post->info->comments_disabled || $post->comments->moderated->count ) :?>
			<div id="post-comments">
			<?php if ( $post->comments->moderated->count ) : ?>
				<?php foreach ( $post->comments->moderated as $comment ) : ?>
				
				<div id="comment-<?php echo $comment->id; ?>" class="post-comment">
					
					<div class="post-comment-commentor">
						<h2>
							<a href="<?php echo $comment->url; ?>" rel="external"><?php echo $comment->name; ?></a>
						</h2>
					</div>
					<div class="post-comment-body">
						<?php echo $comment->content_out; ?>
						<p class="post-comment-link"><a href="#comment-<?php echo $comment->id; ?>" title="Time of this comment - Click for comment permalink"><?php echo $comment->date; ?></a></p>
						<?php if ( $comment->status == Comment::STATUS_UNAPPROVED ) : ?>
						<p class="comment-message"><em><?php _e( 'Your comment is awaiting moderation' ) ;?></em></p>
						<?php endif; ?>
					</div>
				</div>
				<?php endforeach; ?>
			<?php else : ?>
				<h2><?php _e( 'Be the first to write a comment!' ); ?></h2>
			<?php endif; ?>
				<div id="post-comments-footer">
					<!-- TODO: A hook can be placed here-->
				</div>
			</div>
		<?php endif; ?>
