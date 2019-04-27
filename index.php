<?php
get_template_part('parts/header');

while(have_posts()):
  the_post();
  ?>
  <main class="article">
    <h2>
      <a href="<?php the_permalink(); ?>">
        <?php the_title(); ?>
      </a>    
    </h2>
    <?php the_content(); ?>
  </main>
  <?php
endwhile;

get_template_part('parts/footer');
?>