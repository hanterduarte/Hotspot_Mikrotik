import { render, screen } from '@testing-library/react';
import App from './App';

test('renders hotspot title', () => {
  render(<App />);
  const linkElement = screen.getByText(/Hotspot/i);
  expect(linkElement).toBeInTheDocument();
});
