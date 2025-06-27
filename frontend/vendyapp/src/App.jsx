import React, { useState, useEffect } from 'react';
import './App.css';

const API_BASE_URL = 'http://localhost/challenge/backend/api';

const App = () => {
  const [rolls, setRolls] = useState([]);
  const [stats, setStats] = useState({});
  const [filters, setFilters] = useState({ fabric_types: [], colors: [] });
  const [loading, setLoading] = useState(false);
  const [activeTab, setActiveTab] = useState('inventory');
  const [message, setMessage] = useState({ type: '', text: '' });
  
  // Estados para formularios
  const [newRoll, setNewRoll] = useState({
    fabric_type: '',
    color: '',
    length: '',
    entry_date: new Date().toISOString().split('T')[0]
  });
  
  const [sale, setSale] = useState({
    roll_id: '',
    meters_sold: '',
    sale_date: new Date().toISOString().split('T')[0]
  });
  
  const [sortConfig, setSortConfig] = useState({
    order_by: 'entry_date',
    order_dir: 'DESC'
  });

  // Cargar inventario al montar el componente
  useEffect(() => {
    loadInventory();
  }, [sortConfig]);

  // Función para mostrar mensajes temporales
  const showMessage = (type, text) => {
    setMessage({ type, text });
    setTimeout(() => setMessage({ type: '', text: '' }), 5000);
  };

  // Cargar inventario desde la API
  const loadInventory = async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams(sortConfig);
      const response = await fetch(`${API_BASE_URL}/get_inventory.php?${params}`);
      const data = await response.json();
      
      if (data.success) {
        setRolls(data.data);
        setStats(data.stats);
        setFilters(data.filters);
      } else {
        showMessage('error', 'Error al cargar el inventario');
      }
    } catch (error) {
      console.error('Error:', error);
      showMessage('error', 'Error de conexión');
    } finally {
      setLoading(false);
    }
  };

  // Manejar envío del formulario de nuevo rollo
  const handleAddRoll = async (e) => {
    e.preventDefault();
    
    // Validaciones frontend
    if (!newRoll.fabric_type.trim() || !newRoll.color.trim()) {
      showMessage('error', 'Tipo de tela y color son requeridos');
      return;
    }
    
    if (parseFloat(newRoll.length) <= 0) {
      showMessage('error', 'La longitud debe ser mayor a 0');
      return;
    }
    
    setLoading(true);
    try {
      const response = await fetch(`${API_BASE_URL}/add_roll.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(newRoll)
      });
      
      const data = await response.json();
      
      if (data.success) {
        showMessage('success', 'Rollo agregado exitosamente');
        setNewRoll({
          fabric_type: '',
          color: '',
          length: '',
          entry_date: new Date().toISOString().split('T')[0]
        });
        loadInventory();
      } else {
        showMessage('error', data.error || 'Error al agregar el rollo');
      }
    } catch (error) {
      console.error('Error:', error);
      showMessage('error', 'Error de conexión');
    } finally {
      setLoading(false);
    }
  };

  // Manejar envío del formulario de venta
  const handleRegisterSale = async (e) => {
    e.preventDefault();
    
    // Validaciones frontend
    if (!sale.roll_id || !sale.meters_sold) {
      showMessage('error', 'Debe seleccionar un rollo e indicar los metros vendidos');
      return;
    }
    
    if (parseFloat(sale.meters_sold) <= 0) {
      showMessage('error', 'Los metros vendidos deben ser mayor a 0');
      return;
    }
    
    // Validar stock disponible
    const selectedRoll = rolls.find(roll => roll.id === parseInt(sale.roll_id));
    if (selectedRoll && parseFloat(sale.meters_sold) > selectedRoll.current_length) {
      showMessage('error', `No hay suficiente stock. Disponible: ${selectedRoll.current_length} metros`);
      return;
    }
    
    setLoading(true);
    try {
      const response = await fetch(`${API_BASE_URL}/register_sale.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(sale)
      });
      
      const data = await response.json();
      
      if (data.success) {
        showMessage('success', 'Venta registrada exitosamente');
        setSale({
          roll_id: '',
          meters_sold: '',
          sale_date: new Date().toISOString().split('T')[0]
        });
        loadInventory();
      } else {
        showMessage('error', data.error || 'Error al registrar la venta');
      }
    } catch (error) {
      console.error('Error:', error);
      showMessage('error', 'Error de conexión');
    } finally {
      setLoading(false);
    }
  };

  // Manejar cambio de ordenamiento
  const handleSort = (field) => {
    setSortConfig(prev => ({
      order_by: field,
      order_dir: prev.order_by === field && prev.order_dir === 'DESC' ? 'ASC' : 'DESC'
    }));
  };

  return (
    <div className="app">
      <header className="header">
        <h1>Gestión de Inventario Textil</h1>
        <div className="stats">
          <div className="stat-item">
            <span className="stat-label">Total Rollos:</span>
            <span className="stat-value">{stats.total_rolls || 0}</span>
          </div>
          <div className="stat-item">
            <span className="stat-label">Total Metros:</span>
            <span className="stat-value">{stats.total_meters || 0}</span>
          </div>
        </div>
      </header>

      {message.text && (
        <div className={`message ${message.type}`}>
          {message.text}
        </div>
      )}

      <nav className="tabs">
        <button 
          className={activeTab === 'inventory' ? 'active' : ''}
          onClick={() => setActiveTab('inventory')}
        >
          Ver Inventario
        </button>
        <button 
          className={activeTab === 'add-roll' ? 'active' : ''}
          onClick={() => setActiveTab('add-roll')}
        >
          Agregar Rollo
        </button>
        <button 
          className={activeTab === 'register-sale' ? 'active' : ''}
          onClick={() => setActiveTab('register-sale')}
        >
          Registrar Venta
        </button>
      </nav>

      <main className="main-content">
        {activeTab === 'inventory' && (
          <div className="inventory-section">
            <div className="section-header">
              <h2>Inventario Actual</h2>
              <button onClick={loadInventory} disabled={loading} className="refresh-btn">
                {loading ? 'Cargando...' : 'Actualizar'}
              </button>
            </div>

            <div className="table-container">
              <table className="inventory-table">
                <thead>
                  <tr>
                    <th onClick={() => handleSort('fabric_type')} className="sortable">
                      Tipo de Tela {sortConfig.order_by === 'fabric_type' && (sortConfig.order_dir === 'ASC' ? '↑' : '↓')}
                    </th>
                    <th onClick={() => handleSort('color')} className="sortable">
                      Color {sortConfig.order_by === 'color' && (sortConfig.order_dir === 'ASC' ? '↑' : '↓')}
                    </th>
                    <th onClick={() => handleSort('current_length')} className="sortable">
                      Metros Disponibles {sortConfig.order_by === 'current_length' && (sortConfig.order_dir === 'ASC' ? '↑' : '↓')}
                    </th>
                    <th>Longitud Original</th>
                    <th>% Restante</th>
                    <th onClick={() => handleSort('entry_date')} className="sortable">
                      Fecha Ingreso {sortConfig.order_by === 'entry_date' && (sortConfig.order_dir === 'ASC' ? '↑' : '↓')}
                    </th>
                    <th>Estado</th>
                  </tr>
                </thead>
                <tbody>
                  {rolls.length === 0 ? (
                    <tr>
                      <td colSpan="7" className="no-data">
                        {loading ? 'Cargando inventario...' : 'No hay rollos en el inventario'}
                      </td>
                    </tr>
                  ) : (
                    rolls.map(roll => (
                      <tr key={roll.id} className={roll.current_length <= 5 ? 'low-stock' : ''}>
                        <td>{roll.fabric_type}</td>
                        <td>
                          <span className="color-indicator" style={{backgroundColor: roll.color.toLowerCase()}}></span>
                          {roll.color}
                        </td>
                        <td className="number">{parseFloat(roll.current_length).toFixed(2)} m</td>
                        <td className="number">{parseFloat(roll.original_length).toFixed(2)} m</td>
                        <td className="number">
                          <div className="progress-bar">
                            <div 
                              className="progress-fill" 
                              style={{width: `${roll.stock_percentage}%`}}
                            ></div>
                            <span className="progress-text">{roll.stock_percentage}%</span>
                          </div>
                        </td>
                        <td>{new Date(roll.entry_date).toLocaleDateString()}</td>
                        <td>
                          <span className={`status-badge ${roll.current_length <= 5 ? 'low' : roll.current_length <= 15 ? 'medium' : 'good'}`}>
                            {roll.current_length <= 5 ? 'Stock Bajo' : roll.current_length <= 15 ? 'Stock Medio' : 'Stock Bueno'}
                          </span>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>
        )}

        {activeTab === 'add-roll' && (
          <div className="form-section">
            <h2>Agregar Nuevo Rollo</h2>
            <form onSubmit={handleAddRoll} className="roll-form">
              <div className="form-group">
                <label htmlFor="fabric_type">Tipo de Tela *</label>
                <input
                  type="text"
                  id="fabric_type"
                  value={newRoll.fabric_type}
                  onChange={(e) => setNewRoll({...newRoll, fabric_type: e.target.value})}
                  placeholder="Ej: Algodón, Lino, Seda"
                  required
                  maxLength="100"
                />
              </div>

              <div className="form-group">
                <label htmlFor="color">Color *</label>
                <input
                  type="text"
                  id="color"
                  value={newRoll.color}
                  onChange={(e) => setNewRoll({...newRoll, color: e.target.value})}
                  placeholder="Ej: Blanco, Azul, Rojo"
                  required
                  maxLength="50"
                />
              </div>

              <div className="form-group">
                <label htmlFor="length">Longitud del Rollo (metros) *</label>
                <input
                  type="number"
                  id="length"
                  value={newRoll.length}
                  onChange={(e) => setNewRoll({...newRoll, length: e.target.value})}
                  placeholder="Ej: 50.5"
                  step="0.01"
                  min="0.01"
                  required
                />
              </div>

              <div className="form-group">
                <label htmlFor="entry_date">Fecha de Ingreso *</label>
                <input
                  type="date"
                  id="entry_date"
                  value={newRoll.entry_date}
                  onChange={(e) => setNewRoll({...newRoll, entry_date: e.target.value})}
                  max={new Date().toISOString().split('T')[0]}
                  required
                />
              </div>

              <button type="submit" disabled={loading} className="submit-btn">
                {loading ? 'Agregando...' : 'Agregar Rollo'}
              </button>
            </form>
          </div>
        )}

        {activeTab === 'register-sale' && (
          <div className="form-section">
            <h2>Registrar Venta</h2>
            <form onSubmit={handleRegisterSale} className="sale-form">
              <div className="form-group">
                <label htmlFor="roll_id">Seleccionar Rollo *</label>
                <select
                  id="roll_id"
                  value={sale.roll_id}
                  onChange={(e) => setSale({...sale, roll_id: e.target.value})}
                  required
                >
                  <option value="">-- Seleccionar Rollo --</option>
                  {rolls.filter(roll => roll.current_length > 0).map(roll => (
                    <option key={roll.id} value={roll.id}>
                      {roll.fabric_type} - {roll.color} (Disponible: {parseFloat(roll.current_length).toFixed(2)} metros)
                    </option>
                  ))}
                </select>
              </div>

              {sale.roll_id && (
                <div className="roll-info">
                  {(() => {
                    const selectedRoll = rolls.find(roll => roll.id === parseInt(sale.roll_id));
                    return selectedRoll ? (
                      <div className="info-card">
                        <h4>Información del Rollo Seleccionado:</h4>
                        <p><strong>Tipo:</strong> {selectedRoll.fabric_type}</p>
                        <p><strong>Color:</strong> {selectedRoll.color}</p>
                        <p><strong>Stock Disponible:</strong> {parseFloat(selectedRoll.current_length).toFixed(2)} metros</p>
                        <p><strong>Longitud Original:</strong> {parseFloat(selectedRoll.original_length).toFixed(2)} metros</p>
                      </div>
                    ) : null;
                  })()}
                </div>
              )}

              <div className="form-group">
                <label htmlFor="meters_sold">Metros a Vender *</label>
                <input
                  type="number"
                  id="meters_sold"
                  value={sale.meters_sold}
                  onChange={(e) => setSale({...sale, meters_sold: e.target.value})}
                  placeholder="Ej: 10.5"
                  step="0.01"
                  min="0.01"
                  max={sale.roll_id ? rolls.find(roll => roll.id === parseInt(sale.roll_id))?.current_length : undefined}
                  required
                />
              </div>

              <div className="form-group">
                <label htmlFor="sale_date">Fecha de Venta *</label>
                <input
                  type="date"
                  id="sale_date"
                  value={sale.sale_date}
                  onChange={(e) => setSale({...sale, sale_date: e.target.value})}
                  max={new Date().toISOString().split('T')[0]}
                  required
                />
              </div>

              <button type="submit" disabled={loading || !sale.roll_id} className="submit-btn">
                {loading ? 'Registrando...' : 'Registrar Venta'}
              </button>
            </form>
          </div>
        )}
      </main>
    </div>
  );
};

export default App;