
<?php

class PNXTranslator extends \BCLib\PrimoServices\PNXTranslator
{

    /**
     * Translate a single PNX doc into a bib record
     *
     * @param \stdClass $doc a "addDOC" object from a search/view result
     *
     * @return BibRecord[]
     */
    public function translateDoc(\stdClass $doc)
    {
        $bib = parent::translateDoc($doc);
        $record = $doc->PrimoNMBib->record;

        $bib->realfagstermer = $this->extractArray($record->search, 'lsr20');
        $bib->humord = $this->extractArray($record->search, 'lsr14');
        $bib->tekord = $this->extractArray($record->search, 'lsr12');
        $bib->bibsys_id = $this->extractField($record->control, 'sourcerecordid');

        $bib->edition = $this->extractField($record->display, 'edition');
        $bib->rank = floatval($this->extractField($doc, '@RANK'));
        $bib->versions = intval($this->extractField($record->display, 'version'));

        $bib->frbrgroupid = $this->extractField($record->facets, 'frbrgroupid');
        $bib->frbrtype = $this->extractField($record->facets, 'frbrtype');


        // So addLIBRARIES is sometimes a stdObject and sometimes an array of stdObjects.
        // Havent't figured out WHY yet.
        $x = $this->extractField($doc, 'LIBRARIES');
        if ($x) {        
            $x = is_object($x) ? array($x) : $x;
            $libs = array();
            foreach ($x as $lib) {
                foreach ($this->extractArray($lib, 'LIBRARY') as $obj) {
                    $lib = new stdClass;
                    $lib->institution = $this->extractField($obj, 'institution');
                    $lib->library = $this->extractField($obj, 'library');
                    $lib->status = $this->extractField($obj, 'status');
                    $lib->collection = $this->extractField($obj, 'collection');

                    preg_match('/^<span id="dokid">(.*)<\/span>(.*)$$/', $lib->collection, $matches);
                    $lib->dokid = $matches[1];
                    $lib->collection = $matches[2];

                    $lib->callNumber = $this->extractField($obj, 'callNumber');
                    $libs[] = $lib;
                }
            }
        } else {
            $libs = array();
        }
        $bib->libraries = $libs;

        //$bib->cat = floatval($this->extractField($doc->{'GETIT'}[0], '@deliveryCategory'));

        return $bib;
    }

}
