/**
 * @file
 * Javascript for the Contacts Dashboard manage mode.
 */

(function ($, Drupal, drupalSettings, once) {

  /**
   * Update ajax url for Manage Dashboard links.
   *
   * @param block
   *   Dashboard block to update the manage link for.
   */
  function initDashboardManage(block) {
    const destination = 'destination=' + Drupal.encodePath(drupalSettings.path.currentPath);
    const tab = block.getAttribute('data-contacts-manage-block-tab');
    const name = block.getAttribute('data-contacts-manage-block-name');
    const url = [['/admin/contacts/ajax/manage-off-canvas', tab, name].join('/'), destination].join('?');

    // @todo Build link url as part of rendering but keep it hidden.
    block.classList.add('manage-wrapper');
    const link = block.querySelector('.manage-trigger a');
    if (link) {
      link.setAttribute('data-ajax-url', url);
    }

    $(document).trigger('drupalManageLinkAdded', {
      el: block
    });
  }

  /**
   * Scans a particular region for blocks and builds structured data.
   *
   * @param tab
   *   ID of the current Dashboard tab.
   * @param region
   *   ID of the region to build data for.
   * @param ids
   *   The ordered list of block ids.
   *
   * @returns {{tab: string, region: string, blocks: Array}}
   *   Structured data of blocks in region.
   */
  function buildDashboardRegionData(tab, region, ids) {
    const data = {
      'tab': tab,
      'region': region,
      'blocks': []
    };

    for (let weight = 0; weight < ids.length; weight++) {
      const el = document.querySelector('[data-contacts-manage-block-name=' + ids[weight] + ']');

      // @todo check that profile type and relationship are available.
      const block_data = {
        name: ids[weight],
        id: el.getAttribute('data-contacts-manage-block-id'),
        entity_type: el.getAttribute('data-contacts-manage-entity-type'),
        entity_bundle: el.getAttribute('data-contacts-manage-entity-bundle'),
        entity_relationship: el.getAttribute('data-contacts-manage-entity-relationship')
      };
      data.blocks.push(block_data);
    }

    return data;
  }

  /**
   * Update the Dashboard tab with changes made to block contents.
   *
   * @param tab
   *   ID of tab to be updated.
   * @param context
   *   The context of the tab.
   */
  function updateDashboardDrag(tab, context) {
    const dragAreas = context.querySelectorAll('.drag-area');

    if (dragAreas.length === 0) {
      return;
    }

    const regions = [];

    for (let dragArea of dragAreas) {
      // Retrieve the sorted array of block names (retrieved from the data-contacts-manage-block-name attribute)
      const sortedIds = Sortable.get(dragArea).toArray();
      if (sortedIds.length !== 0) {
        const region = dragArea.getAttribute('data-contacts-manage-region-id');
        const data = buildDashboardRegionData(tab, region, sortedIds);
        regions.push(data);
      }
    }

    const url = context.querySelector('[data-contacts-manage-update-url]')
      .getAttribute('data-contacts-manage-update-url');

    $.ajax({
      type: 'POST',
      url: url,
      data: {
        regions: regions,
        tab: tab
      }
    });
  }

  function updateDashboardTabs(context) {
    const dragAreas = context.querySelectorAll('.contacts-ajax-tabs');

    if (dragAreas.length === 0) {
      return;
    }

    for (let dragArea of dragAreas) {
      // Array of tab ids, retreived from the data-contacts-drag-tab-id attribute on each tab.
      const tabIds = Sortable.get(dragArea).toArray();

      $.ajax({
        type: "POST",
        url: "/admin/contacts/ajax/update-tabs",
        data: {tabs: tabIds}
      });
    }
  }

  /**
   * Find all dashboard blocks and set manage ajax links.
   */
  Drupal.behaviors.contactsDashboardManage = {
    attach: function attach(context) {
      const placeholders = once('contextual-render', '[data-contacts-manage-block-name]', context);

      if (placeholders.length === 0) {
        return;
      }

      for (let placeholder of placeholders) {
        const id = placeholder.getAttribute('data-contacts-manage-block-name');
        const matchingPlaceholders = context.querySelectorAll('[data-contacts-manage-block-name="' + id + '"]');

        for (let matchingPlaceholder of matchingPlaceholders) {
          initDashboardManage(matchingPlaceholder);
        }
      }
    }
  };

  /**
   * Set toolbar manage mode ajax link.
   */
  Drupal.behaviors.contactsDashboardManageToolbar = {
    attach: function attach(context) {
      const placeholders = once('toolbar-render', '.toolbar-dashboard-manage', context);

      if (placeholders.length === 0) {
        return;
      }

      for (let placeholder of placeholders) {
        placeholder.setAttribute('data-ajax-url', '/admin/contacts/ajax/manage-mode');
        placeholder.classList.add('use-ajax');
        placeholder.setAttribute('data-ajax-progress', 'fullscreen');

        $(document).trigger('drupalManageTabAdded', {
          el: placeholder
        });
      }
    }
  };

  /**
   * Add sorting of dashboard blocks in manage mode.
   */
  Drupal.behaviors.contactsDashboardManageDragBlocks = {
    attach: function attach(context) {

      var $dragAreas = $(context).find('.drag-area');

      if ($dragAreas.length === 0) {
        return;
      }

      $dragAreas.each(function () {
        Sortable.create(this, {
          group: "dashboard-blocks",
          handle: ".handle",
          draggable: ".draggable",
          dataIdAttr: "data-contacts-manage-block-name",
          onSort: function (event) {
            const tabId = $(event.item)
              .closest("[data-contacts-manage-block-tab]")
              .data('contacts-manage-block-tab');

            updateDashboardDrag(tabId, context);
          }
        });
      });
    }
  };

  /**
   * Add sorting of dashboard blocks in manage mode.
   */
  Drupal.behaviors.contactsDashboardManageDragTabs = {
    attach: function attach(context) {
      var $dragAreas = $(context).find(".contacts-ajax-tabs");

      if ($dragAreas.length === 0) {
        return;
      }

      $dragAreas.each(function () {
        Sortable.create(this, {
          handle: ".drag-handle",
          dataIdAttr: "data-contacts-drag-tab-id",
          onSort: function (event) {
            updateDashboardTabs(context);
          }
        });
      });
    }
  };

  $(document).on('drupalManageLinkAdded', function (event, data) {
    Drupal.ajax.bindAjaxLinks(data.el);
  });

  $(document).on('drupalManageTabAdded', function (event, data) {
    Drupal.ajax.bindAjaxLinks(data.el);
  });

  $(document).ready(function () {
    // Override the prepareDialogButtons behaviour.
    // If a button has a class of "close-dialog" then hook up the close dialog
    // behaviour. Saves needing a separate route & command on the server just
    // to close the dialog.
    var originalPrepareDialogButtons = Drupal.behaviors.dialog.prepareDialogButtons;

    Drupal.behaviors.dialog.prepareDialogButtons = function ($dialog) {
      var buttons = originalPrepareDialogButtons($dialog);
      for (var i = 0; i < buttons.length; i++) {
        if (buttons[i].class.indexOf('close-dialog') !== -1) {
          buttons[i].click = function () {
            $dialog.dialog('close');
          }
        }
      }
      return buttons;
    };

    // If the initial page load is in manage mode, then trigger an ajax request
    // to load the sidebar.
    if (drupalSettings.contacts.manage_mode) {
      Drupal.ajax({url: '/admin/contacts/ajax/manage-sidebar'}).execute();
    }

    // When the sidebar is closed, make sure we exit manage mode.
    // Do this by attaching an event to the close button when the dialog is
    // created. Don't use the dialog:beforeclose event to do this as the close
    // may have been triggered by clicking Manage Dashboard (rathe than the
    // close button), which will cause an infinite loop of closing/opening the
    // sidebar. Only do this if clicking the close button.
    $(window).on('dialog:aftercreate', function (e, dialog, $elem) {
      if ($elem.attr('id') === 'drupal-off-canvas') {
        $elem.parent().find('button.ui-dialog-titlebar-close').click(function () {
          Drupal.ajax({url: '/admin/contacts/ajax/manage-mode'}).execute();
        });
      }
    });
  });

})(jQuery, Drupal, drupalSettings, once);
