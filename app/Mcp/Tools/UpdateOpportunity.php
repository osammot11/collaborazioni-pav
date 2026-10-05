<?php

namespace App\Mcp\Tools;

class UpdateOpportunity extends PipelineTool
{
    protected string $action = 'update';

    protected string $name = 'update_opportunity';

    protected string $description = 'Modifica soltanto i fields forniti (anche note e follow-up). Richiede consenso, id, expected_revision corrente e UUID request_id. Per aggiungere note rileggi e preserva le note esistenti. Non invia email o elimina dati.';
}
