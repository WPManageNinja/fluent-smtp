<?php

namespace WpFluent\QueryBuilder;

class Transaction extends QueryBuilderHandler
{

    /**
     * Commit the database changes
     */
    public function commit()
    {
        $this->db->query('COMMIT');

        throw new TransactionHaltException();
    }

    /**
     * Rollback the database changes
     */
    public function rollback()
    {
        $this->db->query('ROLLBACK');

        throw new TransactionHaltException();
    }
}
