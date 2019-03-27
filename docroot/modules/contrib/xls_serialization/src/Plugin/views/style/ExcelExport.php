<?php

namespace Drupal\xls_serialization\Plugin\views\style;

use Drupal\Core\Form\FormStateInterface;
use Drupal\rest\Plugin\views\style\Serializer;

/**
 * A style plugin for Excel export views.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "excel_export",
 *   title = @Translation("Excel export"),
 *   help = @Translation("Configurable row output for Excel exports."),
 *   display_types = {"data"}
 * )
 */
class ExcelExport extends Serializer {

  /**
   * Constructs a Plugin object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param mixed $serializer
   *   The serializer for the plugin instance.
   * @param array $serializer_formats
   *   The serializer formats for the plugin instance.
   * @param array $serializer_format_providers
   *   The serializer format providers for the plugin instance.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, $serializer, array $serializer_formats, array $serializer_format_providers) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer, $serializer_formats, $serializer_format_providers);

    $this->formats = ['xls', 'xlsx'];
    $this->formatProviders = ['xls' => 'xls_serialization', 'xlsx' => 'xls_serialization'];
  }

  /**
   * {@inheritdoc}
   */
  public function defineOptions() {
    $options = parent::defineOptions();

    $options['xls_settings']['contains'] = [
      'xls_format' => ['default' => 'Excel2007'],
    ];

    $options['xls_settings']['metadata']['contains'] = [
      // The 'created' and 'modified' elements are not exposed here, as they
      // default to the current time (that the spreadsheet is created), and
      // would probably just confuse the UI.
      'creator' => ['default' => ''],
      'last_modified_by' => ['default' => ''],
      'title' => ['default' => ''],
      'description' => ['default' => ''],
      'subject' => ['default' => ''],
      'keywords' => ['default' => ''],
      'category' => ['default' => ''],
      'manager' => ['default' => ''],
      'company' => ['default' => ''],
      // @todo Expose a UI for custom properties.
    ];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    switch ($form_state->get('section')) {
      case 'style_options':
        // Change format to radios instead, since multiple formats here do not
        // make sense as they do for REST exports.
        $form['formats']['#type'] = 'radios';
        $form['formats']['#default_value'] = reset($this->options['formats']);

        // Remove now confusing description.
        unset($form['formats']['#description']);

        // XLS options.
        $xls_options = $this->options['xls_settings'];
        $form['xls_settings'] = [
          '#type' => 'details',
          '#open' => TRUE,
          '#title' => $this->t('XLS(X) settings'),
          '#tree' => TRUE,
          'xls_format' => [
            '#type' => 'select',
            '#title' => $this->t('Format'),
            '#options' => [
              // @todo Add all PHPExcel supported formats.
              'Excel2007' => $this->t('Excel 2007'),
              'Excel5' => $this->t('Excel 5'),
            ],
            '#default_value' => $xls_options['xls_format'],
          ],
        ];
        // XLS metadata.
        $metadata = $xls_options['metadata'];
        $form['xls_settings']['metadata'] = [
          '#type' => 'details',
          '#title' => $this->t('Document metadata'),
          '#open' => !empty(array_filter($metadata)),
        ];
        $form['xls_settings']['metadata']['creator'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Author/creator name'),
          '#default_value' => $metadata['creator'],
        ];
        $form['xls_settings']['metadata']['last_modified_by'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Last modified by'),
          '#default_value' => $metadata['last_modified_by'],
        ];
        $form['xls_settings']['metadata']['title'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Title'),
          '#default_value' => $metadata['title'],
        ];
        $form['xls_settings']['metadata']['description'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Description'),
          '#default_value' => $metadata['description'],
        ];
        $form['xls_settings']['metadata']['subject'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Subject'),
          '#default_value' => $metadata['subject'],
        ];
        $form['xls_settings']['metadata']['keywords'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Keywords'),
          '#default_value' => $metadata['keywords'],
        ];
        $form['xls_settings']['metadata']['category'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Category'),
          '#default_value' => $metadata['category'],
        ];
        $form['xls_settings']['metadata']['manager'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Manager'),
          '#default_value' => $metadata['manager'],
        ];
        $form['xls_settings']['metadata']['company'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Company'),
          '#default_value' => $metadata['company'],
        ];
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    // Transform the formats back into an array.
    $format = $form_state->getValue(['style_options', 'formats']);
    $form_state->setValue(['style_options', 'formats'], [$format => $format]);

    parent::submitOptionsForm($form, $form_state);
  }

}
