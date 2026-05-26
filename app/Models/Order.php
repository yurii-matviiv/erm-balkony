<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    /**
     * ---------------------------------------------------------
     * OLD CRM CONNECTION
     * ---------------------------------------------------------
     */
    protected $connection = 'old_crm';

    /**
     * ---------------------------------------------------------
     * TABLE
     * ---------------------------------------------------------
     */
    protected $table = 'orders';

    /**
     * ---------------------------------------------------------
     * PRIMARY KEY
     * ---------------------------------------------------------
     */
    protected $primaryKey = 'id';

    /**
     * ---------------------------------------------------------
     * TIMESTAMPS
     * ---------------------------------------------------------
     * Таблиця orders не має полів created_at та updated_at
     * ---------------------------------------------------------
     */
    public $timestamps = false;

    /**
     * ---------------------------------------------------------
     * SECURITY
     * ---------------------------------------------------------
     * READ ONLY MODEL
     * ---------------------------------------------------------
     */
    protected $guarded = [];

    /**
     * ---------------------------------------------------------
     * BLOCK WRITE OPERATIONS
     * ---------------------------------------------------------
     * Забороняємо будь-які записи, оновлення та видалення
     * ---------------------------------------------------------
     */
    public function save(array $options = [])
    {
        return false;
    }

    public function delete()
    {
        return false;
    }

    /**
     * ---------------------------------------------------------
     * RELATIONS
     * ---------------------------------------------------------
     */
    
    /**
     * Зв'язок з лідами
     */
    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id', 'id');
    }

    /**
     * Зв'язок з клієнтами
     */
    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id', 'id');
    }

    /**
     * ---------------------------------------------------------
     * ACCESSORS
     * ---------------------------------------------------------
     */
    
    /**
     * Отримати дату створення у форматі d.m.Y
     */
    public function getFormattedCreateDateAttribute(): string
    {
        return $this->create_date ? date('d.m.Y', strtotime($this->create_date)) : '-';
    }

    /**
     * ---------------------------------------------------------
     * SCOPES
     * ---------------------------------------------------------
     */
    
    /**
     * Фільтр по даті створення
     */
    public function scopeWhereDateBetween($query, $from, $to)
    {
        if ($from && $to) {
            return $query->whereDate('create_date', '>=', $from)
                         ->whereDate('create_date', '<=', $to);
        }
        return $query;
    }
}