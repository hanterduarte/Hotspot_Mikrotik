import React, { useState, useEffect } from 'react';
import axios from 'axios';
import './App.css';

function App() {
    const [plans, setPlans] = useState([]);
    const [selectedPlan, setSelectedPlan] = useState(null);
    const [customer, setCustomer] = useState({
        name: '',
        email: '',
        phone: '',
        cpf: ''
    });

    useEffect(() => {
        axios.get('/api/plans/')
            .then(response => {
                setPlans(response.data);
            })
            .catch(error => {
                console.error('There was an error fetching the plans!', error);
            });
    }, []);

    const handlePlanSelect = (plan) => {
        setSelectedPlan(plan);
    };

    const handleInputChange = (e) => {
        const { name, value } = e.target;
        setCustomer({ ...customer, [name]: value });
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        axios.post('/api/process-payment/', {
            plan_id: selectedPlan.id,
            customer: customer
        })
        .then(response => {
            window.location.href = response.data.redirect_url;
        })
        .catch(error => {
            console.error('There was an error processing the payment!', error);
        });
    };

    return (
        <div className="App">
            <header className="App-header">
                <h1>Hotspot</h1>
            </header>
            <div className="container">
                <h2>Planos</h2>
                <div className="plans">
                    {plans.map(plan => (
                        <div key={plan.id} className="plan-card" onClick={() => handlePlanSelect(plan)}>
                            <h3>{plan.name}</h3>
                            <p>Duração: {plan.duration}</p>
                            <p>Preço: R$ {plan.price}</p>
                        </div>
                    ))}
                </div>
                {selectedPlan && (
                    <div className="payment-form">
                        <h3>Plano Selecionado: {selectedPlan.name}</h3>
                        <form onSubmit={handleSubmit}>
                            <input type="text" name="name" placeholder="Nome" value={customer.name} onChange={handleInputChange} required />
                            <input type="email" name="email" placeholder="Email" value={customer.email} onChange={handleInputChange} required />
                            <input type="text" name="phone" placeholder="Telefone" value={customer.phone} onChange={handleInputChange} />
                            <input type="text" name="cpf" placeholder="CPF" value={customer.cpf} onChange={handleInputChange} />
                            <button type="submit">Pagar com InfinityPay</button>
                        </form>
                    </div>
                )}
            </div>
        </div>
    );
}

export default App;
