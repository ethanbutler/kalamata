<?php

add_action('wp_enqueue_scripts', function() {
  wp_enqueue_style('kalamata-css', get_stylesheet_directory_uri() . '/dist/index.css');
  wp_enqueue_script('kalamata-js', get_stylesheet_directory_uri() . '/dist/index.js');
});

$structure = [
  'rule' => [
    'as' => 'hr'
  ],
  'wrap' => [
    'as' => 'article',
    'className' => 'card',
    'children' => [
      'heading' => [
        'className' => 'card-title',
        'children' => [
          'title' => [
            'as' => 'h1',
            'uses' => 'title'
          ]
        ]
      ],
      'description' => [
        'as' => 'div',
        'className' => 'card-description',
        'uses' => 'description'
      ],
      'image-wrap' => [
        'className' => 'card-image',
        'children' => [
          'image' => [
            'uses' => 'thumbnail_id'
          ]
        ]
      ],
      'meta-wrap' => [
        'className' => 'card-meta',
        'children' => [
          'yield' => [
            'uses' => 'yield',
          ],
          'active-time' => [
            'uses' => 'active_time',
          ],
          'total-time' => [
            'uses' => 'total_time',
          ],
        ],
      ],
      'main-wrap' => [
        'className' => 'card-body',
        'children' => [
          'ingredients-wrap' => [
            'className' => 'card-ingredients',
            'children' => [
              'ingredients-title' => [
                'as' => 'h2',
                'children' => 'Ingredients',
              ],
              'ingredients' => [
                'uses' => 'ingredients',
              ]
            ]
          ],
          'instructions-wrap' => [
            'className' => 'card-instructions',
            'children' => [
              'instructions-title' => [
                'as' => 'h2',
                'children' => 'Instructions',
              ],
              'instructions' => [
                'uses' => 'instructions'
              ],
            ]
          ],
        ]
      ],
      'related-wrap' => [
        'className' => 'card-related',
        'children' => [
          'related' => [
            'uses' => ['secondary_term', 'id'],
          ]
        ]
      ],
      'category-wrap' => [
        'className' => 'card-category',
        'children' => [
          'category' => [
            'uses' => 'category'
          ],
        ],
      ],
      'footer' => [
        'as' => 'footer',
        'className' => 'card-footer',
        'children' => [
          'footer-left' => [
            'className' => 'card-footerLeft',
            'children' => [
              'copyright' => [
                'as' => 'span',
                'children' => '&copy'
              ],
              'author' => [
                'as' => 'span',
                'uses' => 'author'
              ]
            ]
          ],
          'footer-right' => [
            'className' => 'card-footerRight',
            'children' => 'Did you make this? Let us know!'
          ],
        ]
      ]
    ]
  ]
];

class Tree {
  function __construct($structure) {
    $this->structure = $structure;
  }

  /**
   * Given a node, extract DOM nodes from attributes as a string of key="value" pairs.
   * @param  {array}  $node
   * @return {string}
   */
  private static function attributes($node) {
    $mapped = ['className' => 'class'];
    $flat = ['id', 'href'];
    $attributes = '';
    foreach($node as $property => $value) {
      if(isset($mapped[$property])) {
        $attributes .= ' '.$mapped[$property].'="'.$value.'"';
      }
      if(in_array($property, $flat) || strpos($property, 'data') === 0) {
        $attributes .= ' '.$property.'="'.$value.'"';
      }
    }

    return $attributes;
  }

  /**
   * Given a node, render it.
   * @param  {string} $key  Node key
   * @param  {array}  $node Node attributes
   * @param  {number} $i    Depth of iterator
   * @return {void}
   */
  private function node($key, $node, $i = 0) {
    // Apply filters to node based on array key
    $node = apply_filters($key, $node, (array) $this->data);
    extract($node); // "as", "uses", "children"

    $as = isset($as) ? $as : 'div';

    // If an action exists for a node, render that.
    if(has_action($key . '-render')) {
      // Generate attributes to pass to render callback
      $atts = is_array($uses)
        // If $uses is an array of data keys, reduce it into an associative array of values
        ? array_reduce($uses, function($array, $use)  {
          $array[$use] = $this->data->{$use};
          return $array;
        }, [])
        // Otherwise, use a single value based on key
        : $this->data->{$uses};
      
      do_action($key . '-render', $atts);

    } else {
      // Otherwise, render the node as a DOM element.

      // Extract attributes from node
      $attributes = Tree::attributes($node);

      // Output opening tag
      echo "<$as$attributes>";

      // If the node has "children", render them
      if (isset($children)) {
        // If children is an array, iterate over each
        if(is_array($children)) {
          $this->iterate($children, $i + 1);
        // Otherwise, output whatever the value of children is – it will be a string
        } else {
          echo $children;
        }
      // If the node doesn't have children but has "uses"
      // output the data value where "uses" is a key
      } elseif(isset($uses)) { 
        echo $this->data->{$uses};
      }
      // Output closing tag
      echo "</$as>";
    }
  }

  /**
   * Given a set of sibling nodes, render render each.
   */
  private function iterate($nodes, $i) {
    foreach($nodes as $key => $node) $this->node($key, $node, $i);
  }

  /**
   * Given a data set, render a DOM tree.
   * @param  {object} $data
   * @return {void}
   */
  public function output($data) {
    $this->data = $data;
    $this->iterate($this->structure, 0);
  }
}

add_shortcode('mv_create', function($atts) use ($structure) {
  // Begin benchmark
  $start = microtime(true);

  // If we're not in the loop, we're probably in Gutenberg
  // or a REST API callback and we don't want to do anything
  if(!in_the_loop()) return;

  // Destucture attributes
  extract($atts); // $key
  $recipe = mv_create_get_creation( $key );

  // Begin output
  ob_start();
  $tree = new Tree($structure);
  $tree->output(json_decode($recipe->published));

  // End benchmark
  if(isset($_GET['debug'])){
    $end = microtime(true);
    echo "\n\n";
    echo 'Rendered in: ';
    echo $end - $start;
    echo ' seconds';
  }

  // Clean and return output
  return ob_get_clean();
});

// Thumbnail output
add_action('image-render', function($thumbnail_id) {
  print wp_get_attachment_image($thumbnail_id);
});

// Ingredient output
add_action('ingredients-render', function($groups) {
  foreach($groups as $id => $ingredients) {
    if($id !== 'mv-has-no-group') {
    ?>
      <h3><?php echo $id; ?></h3>
    <?php
    }
    ?>
      <ul id="<?php echo($id); ?>">
        <?php foreach($ingredients as $ingredient) { ?>
          <li><?php echo $ingredient->original_text; ?></li>
        <?php } ?>
      </ul>
    <?php
  }
});

// Related card output
add_action('related-render', function($atts) {
  global $wpdb;
  extract($atts); // $secondary_term, $id

  // Get up to 3 cards from secondary term, not including current
  $statement = "SELECT canonical_post_id, title from wp_mv_creations WHERE secondary_term = %d AND NOT id = %d LIMIT 3";
  $prepped = $wpdb->prepare($statement, $secondary_term, $id);
  $results = $wpdb->get_results($prepped);

  ?>
    <h2>More <?= get_term($secondary_term)->name; ?> Recipes</h2>
    <ul>
      <?php foreach($results as $result) { ?>
      <li>
        <a href="<?php the_permalink($result->canonical_post_id); ?>">
          <?= $result->title; ?>
        </a>
      </li>
      <?php } ?>
    </ul>
  <?php
});

// Yield output
add_action('yield-render', function($yield) {
  ?>
    <strong>Makes:</strong> <?= $yield; ?><br>
  <?php
});

// Active time output
add_action('active-time-render', function($time) {
  ?>
    <strong>Cook Time:</strong> <?= $time->output; ?><br>
  <?php
});

// Total time output
add_action('total-time-render', function($time) {
  ?>
    <strong>Total Time:</strong> <?= $time->output; ?><br>
  <?php
});

// Category output – include a hashtag and get name
add_action('category-render', function($term_id) {
  ?>
    <span>#<?= get_term($term_id)->name; ?></span>
  <?php
});

// Add a unique ID to each recipe wrap
add_filter('wrap', function($node, $data) {
  $node['id'] = 'recipe-' . $data['id'];
  return $node;
}, 10, 2);

// Add a . to the end of "Ingredients"
add_filter('ingredients-title', function($node) {
  $node['children'] = $node['children'] . '.';
  return $node;
});

// Add a . to the end of "Instructions"
add_filter('instructions-title', function($node) {
  $node['children'] = $node['children'] . '.';
  return $node;
});

// Change DOM node of footer-left to "small"
add_filter('footer-left', function($node) {
  $node['as'] = 'small';
  return $node;
});