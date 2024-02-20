<?php

namespace Drupal\maestro\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Class MaestroTemplateAddForm.
 *
 * Provides the add form for our Template entity.
 *
 * @package Drupal\config_entity_example\Form
 *
 * @ingroup config_entity_example
 */
class MaestroTemplateAddForm extends MaestroTemplateFormBase {

  /**
   * Returns the actions provided by this form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form's form state.
   *
   * @return array
   *   An array of supported actions for the current entity form.
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t('Create Template');
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['description'] = [
      '#markup' => $this->t("Add a Maestro Template Definition"),
    ];
    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * Overrides MaestroTemplateFormBase::save.
   */
  public function save(array $form, FormStateInterface $form_state) {
    parent::save($form, $form_state);

    // In here, we now create the basis for the template tasks.
    $this->entity->tasks['start'] = [
      'id' => 'start',
      'tasktype' => 'MaestroStart',
      'label' => 'Start',
    // Just for starters, the start points directly to the end task.
      'nextstep' => 'end',
      'nextfalsestep' => '',
      'top' => 50,
      'left' => 50,
      'assignby' => 'fixed',
      'assignto' => 'engine',
    ];

    $this->entity->tasks['end'] = [
      'id' => 'end',
      'tasktype' => 'MaestroEnd',
      'label' => 'End',
      'nextstep' => '',
      'nextfalsestep' => '',
      'top' => 200,
      'left' => 200,
      'assignby' => 'fixed',
      'assignto' => 'engine',
    ];

    // Add the two default views.
    $this->entity->views_attached = [
      'maestro_completed_tasks' => [
        'view_machine_name' => 'maestro_completed_tasks',
        'view_weight' => -9,
        'view_display' => 'default;Master',
      ],
      'maestro_entity_identifiers' => [
        'view_machine_name' => 'maestro_entity_identifiers',
        'view_weight' => -10,
        'view_display' => 'taskconsole_display;Task Console Display',
      ],
    ];

    // Add the initiator variable.
    $this->entity->variables['initiator'] = [
      'variable_id' => 'initiator',
      'variable_value' => '0',
    ];

    // Add the workflow_timeline_stage_count variable.
    $this->entity->variables['workflow_timeline_stage_count'] = [
      'variable_id' => 'workflow_timeline_stage_count',
      'variable_value' => '',
    ];

    // Add the workflow_current_stage variable.
    $this->entity->variables['workflow_current_stage'] = [
      'variable_id' => 'workflow_current_stage',
      'variable_value' => '',
    ];

    // Add the workflow_current_stage_message variable.
    $this->entity->variables['workflow_current_stage_message'] = [
      'variable_id' => 'workflow_current_stage_message',
      'variable_value' => '',
    ];

    $this->entity->validated = FALSE;

    $this->entity->save();
  }

}
