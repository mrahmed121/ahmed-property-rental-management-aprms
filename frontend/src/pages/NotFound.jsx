import { Link } from 'react-router-dom';
import Button from '../shared/Button';

export default function NotFound() {
  return (
    <div className="flex min-h-screen flex-col items-center justify-center bg-charcoal-900 px-4 text-center">
      <h1 className="text-6xl font-bold text-gold">404</h1>
      <p className="mt-4 text-lg text-gray-300">Page not found</p>
      <p className="mt-2 text-sm text-gray-500">
        The page you are looking for does not exist or was moved.
      </p>
      <Link to="/" className="mt-6">
        <Button>Back to dashboard</Button>
      </Link>
    </div>
  );
}
