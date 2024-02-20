## Introduction

  * This module uses the Decoupled User Authentication to provide basic Customer
    Relationship Management functionality within core Drupal. By default, 
    enabling the module will create two Profiles and Roles - crm_indiv and 
    crm_org - that can be used to add Drupal users (with or without site logins)
    that are individuals or organisations.

  * The module creates a database search index using Search API DB that indexes 
    all site users, and provides facets to allow the users to be filtered. The 
    search is accessed at /admin/contacts, where local actions exist to add
    organisations or individuals directly. Users can also be added through the 
    core process, where checkboxes exist to mark a user as either Organisation 
    or Individual by role.

  * The module also includes optional sub-modules to provide additional 
    functionality:

    - Contacts DBS

      This integrates Contacts with the UK Disclosure and Barring Service, 
      providing a method of recording and managing DBS returns for your 
      Contacts.

    - Contacts Group

      This extends the module's functionality so that Individual contacts can be
      linked to Organisation contacts, using a Group entity that Contacts are 
      related to.

    - Contacts Log

      This logs certain actions carried out by users in Drupal via the Message 
      module.

    - Contacts Mapping

      This extends the Contacts to provide geocoding information about the 
      Contact addresses, and provide a basic map display when the Contact is 
      viewed.

    - CRM User Dashboard

      This makes changes to the front end user experience, allowing Contacts 
      with a site login to manage their own information without requiring access
      to the admin interface.

## Requirements

  * This module requires the following modules:

    - Address (/project/address)
    - Chaos Tool Suite (ctools) (/project/ctools)
    - CRM Tools (Sub-module included with this module)
    - Decoupled User Authentication (/project/decoupled_auth)
    - Facets (/project/facets)
    - Name Field (/project/name)
    - Profile (/project/profile)
    - Search API (/project/search_api)

  * There are also a number of sub-modules with their own dependencies:
    - CRM Tools
       - Color Field (/project/color_field)
    - Contacts Group
       - Group (/project/group)
    - Contacts Log
       - Message (/project/message)
    - Contacts Mapping
       - Geocoder (/project/geocoder)
       - Geofield (/project/geofield)
       - Leaflet (/project/leaflet)

  * The module also requires the following library when installed via composer:
    - cweagans/composer-patches ([https://github.com/cweagans/composer-patches](https://github.com/cweagans/composer-patches))

## Recommended modules

  * This module also recommends the use of the Contacts Theme
    (/project/contacts_theme), which overrides the default theme on contacts
    pages when enabled. This tidies up the interface for these pages, and
    automatically displays a number of blocks in the correct places. If it isn't
    used, the blocks will require manually enabling for your current
    admin/default theme.

## Installation

  * Because we are currently relying on some core and contrib patches, you will
    need to either manually patch some of our dependencies or use composer with
    the composer patches project. It is recommended to use
    drupal-composer/drupal-project

  * You can add Contacts to your project with the following commands:

        composer config extra.enable-patching true
        composer require drupal/contacts
        drush en contacts

 * Visit [https://www.drupal.org/node/1897420](https://www.drupal.org/node/1897420) for further information.

## Configuration

 * The initial install of the Contacts module requires no additional
   configuration. A view showing all Contacts can be accessed from the
   Administration menu or at /admin/contacts. Permissions to administer, access,
   view and add Contacts should be assigned to the appropriate roles.

 * Settings for the module can be found at /admin/config/contacts, to control
   various related to the behaviour of user login and the admin listing.

 * The view at /admin/contacts uses an automatically created Search API Database
   index. If you wish to use another backend for your search index, you will
   need to replicate the view and its related Facets in your new backend.

 * If you use the Contacts Theme, the facets for the listing at /admin/contacts
   will automatically display in the left-hand column of the page. If you are
   not using the Contacts Theme, these will need to be placed appropriately in
   your preferred admin theme (if required). Additional facets based on your
   contacts' information can be added as usual via the facets user interface.

 * Individual Contacts can be managed from their canonical URI of
   /admin/contacts/{id}. Tabs will display depending on the modules that are
   enabled, allowing you to add/edit the individual or organisation profiles,
   find duplicate Contacts, manage DBS records and link Individuals to their
   Organisations.

 * Users with the manage contacts dashboard permission can alter the existing
   tabs at /admin/structure/contact-tabs. Adding new tabs requires developer
   intervention to identify the content to be displayed on the tab. Existing
   tabs can be used as a guide for creating additional ones.

 * Contacts DBS sub-module

   This module requires no additional configuration. Once enabled, it adds a DBS
   tab to the /admin/contacts/{uid} page where details of different DBS types
   can be added. Default DBS types with default expiry periods are added on
   install, and can be managed at /admin/structure/dbs-workforce. New types can
   also be added by users with the Administer DBS workforce entities permission.

 * Contacts Group

   This module requires no additional configuration. Once enabled, it adds an
   Organisations tab to all Individual users where individuals can be linked to
   Organisations. Details of the nature of the relationship can also be added
   when it is created.

 * Contacts Log

   This module requires no additional configuration. Once enabled, it adds a Log
   tab to all contacts, where information about changes made to the Contact is
   recorded. The message module provides settings at
   /admin/config/message/message that can impact the logging.

 * Contacts Mapping

   Once enabled, this module adds a Geofield to the Individual and Organisation
   Contacts, linked to their address field. The address will be geocoded on user
   save. In order for the geocoding to function, a Geocoder Plugin will need to
   be configured at /admin/config/system/geocoder - instructions for doing this
   are provided by the Geocoder module.

   An "Override geolocation" checkbox is also available on Contacts. Checking it
   will reveal a field to manually enter geocoded location data for the Contact.
   This is to allow faulty geocoding to be corrected manually by admins.

 * CMS User Dashboard

   This module requires no additional configuration. Once enabled, it provides
   additional user-facing routes to allow site users to manage their own
   Contacts records. A new link is added to the Contact admin interface at
   /admin/contacts/{uid} to allow users with the access user dashboards
   permission to view the contact as the user will see it. The default path for
   a user facing Contact is /user/{uid}/summary: this provides tabs for the user
   to manage their own contact profile and user account.

   Users with the manage contacts dashboard permission can alter the existing
   tabs at /admin/structure/contact-tabs. Adding new tabs requires developer
   intervention to identify the content to be displayed on the tab. Existing
   tabs can be used as a guide for creating additional ones.

   The module will add additional configuration options to the form at
   /admin/config/contacts to all users to be directed to their dashboard if they
   try to directly access their user accounts.

## Troubleshooting

 * If you visit /admin/contacts and do not see any contacts listed, visit
   /admin/config/search/search-api/index/contacts_index and ensure that the
   search index has run.

 * If you have Contacts Mapping enabled and receive an error about geocoding
   when Contacts are saved, make sure you have correctly configured the Geocoder
   module according to its instructions.

## FAQ


 Q: How can I extend the functionality of the Contacts module?
 A: As Contacts are core Drupal User entities, any contrib or custom that
    extends User functionality can be used with the Contacts module. The
    relationship between Individuals and Organisations can be extended with any
    Group related modules. Contacts and their relationships are fieldable
    entities which can be managed as normal through the Drupal user interface.

## Maintainers

Current maintainers:
 * Andrew Belcher (andrewbelcher) - /u/andrewbelcher
 * Yan Loetzer (yanniboi) - /u/yanniboi
 * Paul Smith (MrDaleSmith) - /u/mrdalesmith

This project has been sponsored by:
 * Freely Give
   We’re FreelyGive, Drupal specialists developing Full Stack Customer
   Relationship Management (CRM) solutions your users will love.
   Visit [https://freelygive.io/](https://freelygive.io/) for more information.
