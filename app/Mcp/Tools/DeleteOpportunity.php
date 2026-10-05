<?php

namespace App\Mcp\Tools;

class DeleteOpportunity extends PipelineTool
{
    protected string $action = 'delete';

    protected string $name = 'delete_opportunity';

    protected string $description = 'Elimina definitivamente un singolo contatto/opportunità e il suo storico di pipeline. '
        .'Usa solo dopo una richiesta esplicita e conferma dell’utente per quel contatto. Prima chiama get_opportunity: '
        .'mostra nome e ID all’utente, spiega che non esiste ripristino nell’app e chiedi conferma. '
        .'Richiede id, expected_revision corrente, confirm_name uguale al nome letto e UUID request_id. '
        .'Conserva una traccia nel registro integrazioni. Non supporta eliminazioni massive.';
}
