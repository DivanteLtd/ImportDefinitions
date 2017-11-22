/**
 * Import Definitions.
 *
 * LICENSE
 *
 * This source file is subject to the GNU General Public License version 3 (GPLv3)
 * For the full copyright and license information, please view the LICENSE.md and gpl-3.0.txt
 * files that are distributed with this source code.
 *
 * @copyright  Copyright (c) 2016-2017 W-Vision (http://www.w-vision.ch)
 * @license    https://github.com/w-vision/ImportDefinitions/blob/master/gpl-3.0.txt GNU General Public License version 3 (GPLv3)
 */

pimcore.registerNS('pimcore.plugin.importdefinitions.interpreters.hrefgetby');

pimcore.plugin.importdefinitions.interpreters.hrefgetby = Class.create(pimcore.plugin.importdefinitions.interpreters.abstract, {

    getLayout : function (fromColumn, toColumn, record, config) {
        var classesStore = new Ext.data.JsonStore({
            autoDestroy: true,
            proxy: {
                type: 'ajax',
                url: '/admin/class/get-tree'
            },
            fields: ['text','field']
        });
        classesStore.load();

        return [{
            xtype : 'combo',
            fieldLabel: t('class'),
            name: 'class',
            displayField: 'text',
            valueField: 'text',
            store: classesStore,
            width: 500,
            value : config.class ? config.class : null
        },
            {
                xtype : 'textfield',
                fieldLabel: t('field'),
                name: 'field',
                displayField: 'text',
                valueField: 'text',
                width: 500,
                value : config.field ? config.field : null
            }];
    }
});
