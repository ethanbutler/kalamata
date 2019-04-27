<?php
get_template_part('parts/header');

while(have_posts()):
  the_post();
  ?>
  <main class="article">
    <h1><?php the_title(); ?></h1>
    <?php the_content(); ?>
  </main>
  <?php
endwhile;

get_template_part('parts/footer');
?>