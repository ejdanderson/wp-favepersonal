<?php

include(CFCT_PATH.'header/featured/init.php');

?>
				<article id="featured-post-<?php echo $slot; ?>" class="featured <?php echo $class; ?>">
					<?php echo $image; ?>
					<div class="featured-content">
						<h2 class="featured-title"><?php the_title(); ?></h2>
						<div class="featured-description">
							<?php the_excerpt(); ?>							
						</div>
					</div>
					<a href="<?php the_permalink(); ?>" class="featured-link"><?php _e('Read More', 'favepersonal'); ?></a>
				</article><!-- .featured -->
