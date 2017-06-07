<?php

namespace Icinga\Module\Puppetdb\ProvidedHook\Director;

use Icinga\Module\Director\Hook\ImportSourceHook;
use Icinga\Module\Director\Web\Form\QuickForm;
use Icinga\Module\Puppetdb\PuppetDbApi;
use Icinga\Module\Puppetdb\PuppetDb;
use Icinga\Application\Benchmark;
use Exception;

/**
 * Class ImportSource
 * @package Icinga\Module\Puppetdb\ProvidedHook\Director
 */
class ImportSource extends ImportSourceHook
{
    /** @var PuppetDbApi */
    protected $db;

    /**
     * @inheritdoc
     */
    public function fetchData()
    {

        $db = $this->db();
        $result = array();
        foreach ($this->listColumns() as $column) {

            $facts = $db->getFact($column);

            foreach ( $facts as $res) {
                if ( ! isset($result[$res->certname]) ) {
                    $result[$res->certname] = array(
                        'certname' => $res->certname
                    );
                }
                $result[$res->certname][$res->name] = $res->value;
            }
        }

        $final = array();

        foreach ( $result as $res ) {
                $final[] = (object)$res;
        }

        return $final;
    }

    /**
     * @inheritdoc
     */
    public function listColumns()
    {

        $columns = array();
        $columns[] = 'certname';

        $facts = preg_split('/,\s*/', $this->settings['queried_facts'], -1, PREG_SPLIT_NO_EMPTY);

        foreach ($facts as $fact) {
            $columns[] = $fact;
        }
        return $columns;
    }

    /**
     * @inheritdoc
     */
    public static function getDefaultKeyColumnName()
    {
        return 'certname';
    }

    /**
     * @inheritdoc
     */
    public static function addSettingsFormFields(QuickForm $form)
    {
        /** @var $form \Icinga\Module\Director\Forms\ImportSourceForm */
        $pdb = new PuppetDb();
        $form->addElement('select', 'api_version', array(
            'label'        => 'API version',
            'required'     => true,
            'multiOptions' => array(
                'v4' => 'v4: PuppetDB 2.3, 3.0, 3.1, 3.2, 4.0 (PE 3.8, 2015.2, 2015.3)',
                'v3' => 'v3: PuppetDB 1.5, 1.6 (PE 3.1, 3.2, 3.3)',
                'v2' => 'v2: PuppetDB 1.1, 1.2, 1.3, 1.4',
                'v1' => 'v1: PuppetDB 1.0',
            ),
        ));

        $form->addElement('select', 'server', array(
            'label'        => 'PuppetDB Server',
            'required'     => true,
            'multiOptions' => $form->optionalEnum($pdb->listServers()),
            'class'        => 'autosubmit',
        ));

        if (! ($server = $form->getSentOrObjectSetting('server'))) {
            return;
        }

        $form->addElement('select', 'client_cert', array(
            'label'        => 'Client Certificate',
            'required'     => true,
            'class'        => 'autosubmit',
            'multiOptions' => $form->optionalEnum($pdb->listClientCerts($server)),
        ));

        if (! ($cert = $form->getSentOrObjectSetting('client_cert'))) {
            return;
        }

        $allowed_facts_name = array(
            'server_class' => 'Server class',
            'server_type'  => 'Server type',
        );

        $form->addElement('textarea', 'queried_facts', array(
            'label'        => 'List of facts to query',
            'required'     => true,
            'class'        => 'autosubmit',
            'rows'         => 5,
            'spellcheck'   => 'false',
            'description'  => 'List of comma-separated facts like:'
                . 'ipaddress_eth0, puppetversion',
        ));

        return;
    }

    /**
     * @return PuppetDbApi
     */
    protected function db()
    {
        if ($this->db === null) {
            $this->db = new PuppetDbApi(
                $this->getSetting('api_version'),
                $this->getSetting('client_cert'),
                $this->getSetting('server')
            );
        }

        return $this->db;
    }
}
