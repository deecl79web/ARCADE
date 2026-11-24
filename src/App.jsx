import { Route, Routes } from 'react-router-dom';
import Layout from './components/Layout';
import ProtectedRoute from './components/ProtectedRoute';
import Home from './pages/Home';
import Legal from './pages/Legal';
import Login from './pages/Login';
import Register from './pages/Register';
import Arcade from './pages/Arcade';
import GamePage from './pages/GamePage';
import Dashboard from './pages/Dashboard';
import Vouchers from './pages/Vouchers';
import AdminScan from './pages/admin/AdminScan';
import AdminVouchers from './pages/admin/AdminVouchers';
import AdminVoucherTypes from './pages/admin/AdminVoucherTypes';
import AdminGames from './pages/admin/AdminGames';
import AdminAds from './pages/admin/AdminAds';
import AdminNetworkAds from './pages/admin/AdminNetworkAds';
import AdminEconomy from './pages/admin/AdminEconomy';

export default function App() {
  return (
    <Routes>
      <Route element={<Layout />}>
        <Route path="/" element={<Home />} />
        <Route path="/legal/:page" element={<Legal />} />
        <Route path="/login" element={<Login />} />
        <Route path="/register" element={<Register />} />
        <Route path="/arcade" element={<Arcade />} />

        <Route element={<ProtectedRoute />}>
          <Route path="/arcade/:slug" element={<GamePage />} />
          <Route path="/dashboard" element={<Dashboard />} />
          <Route path="/vouchers" element={<Vouchers />} />
        </Route>

        <Route element={<ProtectedRoute admin />}>
          <Route path="/admin/economy" element={<AdminEconomy />} />
          <Route path="/admin/games" element={<AdminGames />} />
          <Route path="/admin/ads" element={<AdminAds />} />
          <Route path="/admin/network-ads" element={<AdminNetworkAds />} />
          <Route path="/admin/scan" element={<AdminScan />} />
          <Route path="/admin/vouchers" element={<AdminVouchers />} />
          <Route path="/admin/voucher-types" element={<AdminVoucherTypes />} />
        </Route>
      </Route>
    </Routes>
  );
}
